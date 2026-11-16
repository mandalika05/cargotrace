<?php
/*
 * ============================================================
 * BUAT APA : Mengisi pengaturan awal (ambang delay). Nilainya diambil dari
 *            config/cargotrace.php. firstOrCreate = tidak menimpa nilai yang
 *            sudah diubah Admin lewat Settings.
 * ============================================================
 */

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    // Mengisi ambang delay awal dari config/cargotrace.php. firstOrCreate = baris yang sudah ada (mungkin sudah diubah Admin) tidak ditimpa.
    public function run(): void
    {
        $defaults = [
            'delay_low_max_hours' => config('cargotrace.delay.low_max_hours'),
            'delay_medium_max_hours' => config('cargotrace.delay.medium_max_hours'),
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => (string) $value]);
        }
    }
}
