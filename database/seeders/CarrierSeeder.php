<?php
/*
 * ============================================================
 * BUAT APA : Data demo carrier (perusahaan fiktif, bukan perusahaan sungguhan).
 * ============================================================
 */

namespace Database\Seeders;

use App\Models\Carrier;
use Illuminate\Database\Seeder;

class CarrierSeeder extends Seeder
{
    public function run(): void
    {
        $carriers = [
            ['Nusantara Freight Lines', 'NFL', 'ops@nusantara-freight.example'],
            ['Oceanic Express',         'OCX', 'desk@oceanic-express.example'],
            ['Meridian Cargo',          'MRC', 'hello@meridian-cargo.example'],
            ['Harbor & Co Logistics',   'HCL', 'ops@harborco.example'],
        ];

        foreach ($carriers as [$name, $code, $email]) {
            Carrier::firstOrCreate(['code' => $code], ['name' => $name, 'contact_email' => $email, 'is_active' => true]);
        }
    }
}
