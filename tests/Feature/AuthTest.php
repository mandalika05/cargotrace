<?php
/*
 * ============================================================
 * BUAT APA : Menguji autentikasi (syarat brief: login valid/tidak valid
 *            dan akses tanpa login).
 * ISI TES  : login benar, login salah, tamu ditolak di dashboard,
 *            daftar selalu jadi customer (anti mass-assignment), logout.
 * RefreshDatabase : database dikosongkan & dimigrasi ulang di tiap tes.
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_shown_to_guests(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->operator()->create(['email' => 'ops@example.test']);

        $this->post('/login', ['email' => 'ops@example.test', 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'ops@example.test']);

        $this->from('/login')
            ->post('/login', ['email' => 'ops@example.test', 'password' => 'salah-banget'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_guest_cannot_open_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_logged_in_user_can_open_dashboard(): void
    {
        $this->actingAs(User::factory()->customer()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Signed in as customer');
    }

    public function test_registration_always_creates_a_customer_even_if_role_is_sent(): void
    {
        $this->post('/register', [
            'name' => 'Penyerang',
            'email' => 'hacker@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin', // percobaan mass-assignment
        ])->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', ['email' => 'hacker@example.test', 'role' => 'customer']);
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $this->post('/register', [
            'name' => 'Budi',
            'email' => 'budi@example.test',
            'password' => 'password123',
            'password_confirmation' => 'beda',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'budi@example.test']);
    }

    public function test_user_can_logout(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }
}
