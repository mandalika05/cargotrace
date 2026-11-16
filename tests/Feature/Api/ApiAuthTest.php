<?php
/*
 * ============================================================
 * BUAT APA : Menguji REST API: login token Sanctum, register (selalu customer),
 *            logout, dan penolakan tanpa token (syarat brief: REST API + token).
 * ============================================================
 */

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_a_bearer_token_that_works(): void
    {
        $user = User::factory()->operator()->create(['email' => 'op@example.test']);

        $response = $this->postJson('/api/v1/auth/login', ['email' => 'op@example.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.role', 'operator');

        $token = $response->json('token');
        $this->assertNotEmpty($token);

        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonMissingPath('data.password');
    }

    public function test_wrong_credentials_give_401_with_a_generic_message(): void
    {
        User::factory()->create(['email' => 'a@example.test']);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.test', 'password' => 'wrong'])
            ->assertStatus(401)->assertJsonPath('message', 'These credentials do not match our records.');
        $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.test', 'password' => 'wrong'])
            ->assertStatus(401)->assertJsonPath('message', 'These credentials do not match our records.');
        $this->postJson('/api/v1/auth/login', [])->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_protected_endpoints_need_a_token(): void
    {
        $this->getJson('/api/v1/shipments')->assertStatus(401);
        $this->getJson('/api/v1/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_register_always_creates_a_customer(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'New Person', 'email' => 'new@example.test',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'role' => 'admin', // harus diabaikan
        ])->assertCreated()->assertJsonPath('user.role', 'customer');

        $this->assertSame('customer', User::where('email', 'new@example.test')->value('role'));
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::factory()->create(['email' => 'x@example.test']);
        $token = $this->postJson('/api/v1/auth/login', ['email' => 'x@example.test', 'password' => 'password'])->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unknown_resources_give_a_clean_404(): void
    {
        $user = User::factory()->operator()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/shipments/999999')
            ->assertStatus(404)->assertExactJson(['message' => 'Resource not found.']);
    }
}
