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
        ]);
    }
}
