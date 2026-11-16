<?php
/*
 * ============================================================
 * BUAT APA : Mengisi data awal: akun demo + carrier + lokasi + shipment.
 *            Jalankan: php artisan migrate:fresh --seed  (hanya di laptop!)
 * AKUN DEMO: password semuanya 'password' (hanya untuk demo lokal).
 * ============================================================
 */

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Mengisi data demo: 5 akun, lalu carrier, lokasi, shipment, riwayat tracking, pengaturan delay, dan exception.
    // Urutan di daftar call() penting: exception butuh shipment dan tracking yang sudah ada.
    public function run(): void
    {
        $akunDemo = [
            ['Demo Admin',            'admin@cargotrace.test',    User::ROLE_ADMIN],
            ['Demo Operator',         'operator@cargotrace.test', User::ROLE_OPERATOR],
            ['Demo Customer',         'customer@cargotrace.test', User::ROLE_CUSTOMER],
            ['Sinar Mandiri Trading', 'sinar@cargotrace.test',    User::ROLE_CUSTOMER],
            ['Orchid Export Co',      'orchid@cargotrace.test',   User::ROLE_CUSTOMER],
        ];

        foreach ($akunDemo as [$name, $email, $role]) {
            if (! User::where('email', $email)->exists()) {
                User::factory()->create(['name' => $name, 'email' => $email, 'role' => $role]);
            }
        }

        $this->call([
            CarrierSeeder::class,
            LocationSeeder::class,
            ShipmentSeeder::class,
            TrackingEventSeeder::class,
            SettingSeeder::class,
            ExceptionSeeder::class,
        ]);
    }
}
