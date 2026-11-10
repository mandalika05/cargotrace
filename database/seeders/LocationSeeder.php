<?php
/*
 * ============================================================
 * BUAT APA : Data demo lokasi pada rute Asia Tenggara -> Eropa (skenario Group A).
 * CATATAN  : koordinat adalah perkiraan titik pelabuhan, cukup untuk peta demo.
 * ============================================================
 */

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            // nama,               kota,        negara,           lat,        lng
            ['Singapore Port',     'Singapore', 'Singapore',      1.2644,     103.8200],
            ['Port Klang',         'Klang',     'Malaysia',       3.0000,     101.3900],
            ['Tanjung Priok',      'Jakarta',   'Indonesia',     -6.1049,     106.8850],
            ['Belawan Port',       'Medan',     'Indonesia',      3.7860,     98.6860],
            ['Laem Chabang Port',  'Chonburi',  'Thailand',      13.0800,     100.8800],
            ['Rotterdam Port',     'Rotterdam', 'Netherlands',   51.9500,     4.1400],
            ['Hamburg Port',       'Hamburg',   'Germany',       53.5400,     9.9700],
            ['Antwerp Port',       'Antwerp',   'Belgium',       51.2700,     4.3400],
            ['Felixstowe Port',    'Felixstowe', 'United Kingdom', 51.9500,   1.3500],
        ];

        foreach ($locations as [$name, $city, $country, $lat, $lng]) {
            Location::firstOrCreate(
                ['name' => $name, 'city' => $city],
                ['country' => $country, 'latitude' => $lat, 'longitude' => $lng]
            );
        }
    }
}
