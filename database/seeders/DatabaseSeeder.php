<?php
/*
 * ============================================================
 * BUAT APA : Mengisi data awal (akun demo untuk dosen/recruiter).
 *            Jalankan: php artisan db:seed  (atau migrate:fresh --seed)
 * AKUN DEMO: password semuanya 'password' (hanya untuk demo lokal).
 * AMAN DIULANG: akun yang sudah ada tidak dibuat dua kali.
 * ============================================================
 */

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $akunDemo = [
            ['Demo Admin',    'admin@cargotrace.test',    User::ROLE_ADMIN],
            ['Demo Operator', 'operator@cargotrace.test', User::ROLE_OPERATOR],
            ['Demo Customer', 'customer@cargotrace.test', User::ROLE_CUSTOMER],
        ];

        foreach ($akunDemo as [$name, $email, $role]) {
            if (! User::where('email', $email)->exists()) {
                // Factory boleh mengisi 'role' walau tidak ada di $fillable
                User::factory()->create(['name' => $name, 'email' => $email, 'role' => $role]);
            }
        }
    }
}
