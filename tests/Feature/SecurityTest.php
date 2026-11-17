<?php
/*
 * ============================================================
 * BUAT APA : Menguji keamanan (P14), sesuai brief: password di-hash, proteksi XSS dan
 *            SQL injection, mass-assignment, tidak ada stack trace, batas percobaan login,
 *            header keamanan, token API kedaluwarsa, dan akses tanpa izin.
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_sent_on_web_and_api(): void
    {
        foreach ([$this->get('/login'), $this->getJson('/api/v1/auth/me')] as $response) {
            $response->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
            $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
        }
    }

    public function test_hsts_is_sent_only_over_https(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }

    public function test_web_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-pass'])->assertStatus(302);
        }

        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-pass'])->assertStatus(429);
    }

    public function test_api_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-pass'])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-pass'])->assertStatus(429);
    }

    public function test_passwords_are_hashed_and_never_returned(): void
    {
        $user = User::factory()->customer()->create(['password' => 'Plain-Pass-123']);

        $this->assertNotSame('Plain-Pass-123', $user->getRawOriginal('password'));
        $this->assertStringStartsWith('$2y$', $user->getRawOriginal('password'));

        $json = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me')->assertOk()->getContent();
        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString('remember_token', $json);
    }

    public function test_user_input_is_escaped_in_pages(): void
    {
        $payload = '<script>alert("xss")</script>';
        $admin = User::factory()->admin()->create();
        $shipment = Shipment::factory()->create(['cargo_type' => $payload]);

        $page = $this->actingAs($admin)->get(route('shipments.show', $shipment))->assertOk();

        $page->assertDontSee($payload, false);
        $page->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
    }

    public function test_sql_injection_strings_are_treated_as_plain_text(): void
    {
        $admin = User::factory()->admin()->create();
        Shipment::factory()->count(2)->create();

        $evil = "' OR 1=1; DROP TABLE shipments; --";

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/shipments?q='.urlencode($evil))
            ->assertOk()->assertJsonCount(0, 'data');

        $this->assertSame(2, Shipment::count()); // tabel masih utuh
    }

    public function test_sort_and_filter_values_come_from_a_whitelist(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/shipments?sort='.urlencode('id; DROP TABLE users'))
            ->assertStatus(422)->assertJsonValidationErrors('sort');
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/shipments?status=HACKED')
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_mass_assignment_cannot_set_role_or_protected_fields(): void
    {
        $this->post('/register', [
            'name' => 'Mallory', 'email' => 'mallory@example.com',
            'password' => 'Password-123', 'password_confirmation' => 'Password-123',
            'role' => 'admin', 'is_admin' => true,
        ]);

        $this->assertSame(User::ROLE_CUSTOMER, User::where('email', 'mallory@example.com')->value('role'));
    }

    public function test_api_errors_never_show_a_stack_trace(): void
    {
        config(['app.debug' => false]);
        Route::get('/api/v1/__boom', fn () => throw new \RuntimeException('secret internal detail'));

        $body = $this->getJson('/api/v1/__boom')->assertStatus(500)->getContent();

        $this->assertStringNotContainsString('secret internal detail', $body);
        $this->assertStringNotContainsString('trace', strtolower($body));
        $this->assertStringNotContainsString('RuntimeException', $body);
    }

    public function test_unknown_api_resources_return_a_clean_404(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/v1/shipments/999999')
            ->assertStatus(404)->assertExactJson(['message' => 'Resource not found.']);
    }

    public function test_every_private_page_redirects_guests_to_login(): void
    {
        foreach (['dashboard', 'shipments.index', 'exceptions.index', 'notifications.index', 'attention.index', 'carriers.index', 'locations.index', 'users.index', 'settings.edit', 'audit-logs.index'] as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
    }

    public function test_every_private_api_endpoint_needs_a_token(): void
    {
        foreach (['/shipments', '/exceptions', '/dashboard', '/attention', '/notifications', '/audit-logs', '/carriers', '/locations', '/settings/delay'] as $path) {
            $this->getJson('/api/v1'.$path)->assertUnauthorized();
        }
    }

    public function test_api_tokens_expire(): void
    {
        $this->assertSame(10080, (int) config('sanctum.expiration'));

        $user = User::factory()->customer()->create();
        $token = $user->createToken('t');
        $token->accessToken->forceFill(['created_at' => now()->subMinutes(10081)])->save();

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_session_cookie_is_http_only(): void
    {
        $this->assertTrue((bool) config('session.http_only'));
        $this->assertContains(config('session.same_site'), ['lax', 'strict']);
    }
}
