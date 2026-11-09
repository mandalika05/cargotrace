<?php
/*
 * ============================================================
 * BUAT APA : Menguji penjaga peran: tamu ditolak, peran salah ditolak,
 *            peran benar diizinkan. Brief dosen minta "uji akses tanpa izin".
 * POLA DARI: UTS MedikaCare (tes akses peran).
 * SYARAT   : RoleMiddleware sudah didaftarkan (bootstrap/app.php), kolom 'role'
 *            ada di tabel users, dan UserFactory memakai versi di folder 1.
 * CATATAN  : Route uji dibuat di dalam tes, jadi tidak perlu halaman asli.
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase; // database dikosongkan & dimigrasi ulang tiap tes

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'role:admin'])->get('/_tes/admin', fn () => 'ok');
        Route::middleware(['web', 'role:admin,operator'])->get('/_tes/staf', fn () => 'ok');
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/_tes/admin')->assertRedirect('/login');
    }

    public function test_customer_ditolak_di_halaman_admin(): void
    {
        $this->actingAs(User::factory()->customer()->create())
            ->get('/_tes/admin')->assertForbidden(); // 403
    }

    public function test_admin_boleh_masuk_halaman_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/_tes/admin')->assertOk();
    }

    public function test_operator_boleh_di_halaman_staf_tapi_tidak_di_admin(): void
    {
        $operator = User::factory()->operator()->create();

        $this->actingAs($operator)->get('/_tes/staf')->assertOk();
        $this->actingAs($operator)->get('/_tes/admin')->assertForbidden();
    }

    public function test_permintaan_json_mendapat_respons_json(): void
    {
        $this->getJson('/_tes/admin')->assertUnauthorized(); // 401

        $this->actingAs(User::factory()->customer()->create())
            ->getJson('/_tes/admin')
            ->assertForbidden()
            ->assertJson(['message' => 'Forbidden.']);
    }
}
