<?php
/*
 * ============================================================
 * BUAT APA : Shipment demo dengan berbagai status supaya daftar terlihat nyata.
 * CATATAN  : tanggal dihitung dari "sekarang" sehingga selalu relevan.
 *            Beberapa shipment IN_TRANSIT sengaja punya ETA yang melewati jadwal
 *            (untuk demo deteksi delay di P11). Riwayat tracking yang cocok
 *            dengan status ditambahkan di P10.
 * forceCreate: melewati $fillable karena seeder yang berhak mengisi status.
 * ============================================================
 */

namespace Database\Seeders;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentService;
use Illuminate\Database\Seeder;

class ShipmentSeeder extends Seeder
{
    public function run(): void
    {
        if (Shipment::exists()) {
            return; // jangan dobel kalau seeder dijalankan lagi
        }

        $service = app(ShipmentService::class);
        $operator = User::where('email', 'operator@cargotrace.test')->first();
        $customers = User::where('role', User::ROLE_CUSTOMER)->orderBy('id')->get();
        $carriers = Carrier::orderBy('id')->get();
        $loc = fn (string $name) => Location::where('name', $name)->first();

        // [customer#, carrier#, asal, tujuan, barang, kg, m3, berangkat(hari), lama(hari), status, telat(jam)]
        $rows = [
            [0, 0, 'Singapore Port',    'Rotterdam Port', 'Electronics',       4200,  28, -35, 24, 'DELIVERED',  0],
            [1, 1, 'Port Klang',        'Hamburg Port',   'Rubber products',  18000,  55, -33, 26, 'DELIVERED',  0],
            [2, 2, 'Tanjung Priok',     'Antwerp Port',   'Furniture',         9600,  47, -30, 27, 'DELIVERED',  9],
            [0, 0, 'Belawan Port',      'Rotterdam Port', 'Coffee beans',     15000,  38, -12, 24, 'IN_TRANSIT', 0],
            [1, 1, 'Singapore Port',    'Hamburg Port',   'Machinery parts',   7800,  33,  -9, 22, 'IN_TRANSIT', 5],
            [2, 3, 'Laem Chabang Port', 'Felixstowe Port', 'Textiles',         5200,  30, -10, 25, 'IN_TRANSIT', 15],
            [0, 2, 'Port Klang',        'Antwerp Port',   'Palm oil derivatives', 21000, 60, -8, 23, 'IN_TRANSIT', 30],
            [1, 0, 'Tanjung Priok',     'Rotterdam Port', 'Textiles',          3100,  22,  -1, 25, 'PICKED_UP',  0],
            [2, 1, 'Singapore Port',    'Antwerp Port',   'Electronics',       2400,  14,   2, 24, 'BOOKED',     0],
            [0, 3, 'Belawan Port',      'Hamburg Port',   'Coffee beans',     12000,  36,   3, 26, 'BOOKED',     0],
            [1, 2, 'Laem Chabang Port', 'Rotterdam Port', 'Machinery parts',   6400,  29,   6, 25, 'CREATED',    0],
            [2, 0, 'Port Klang',        'Felixstowe Port', 'Furniture',        8800,  44,   7, 27, 'CREATED',    0],
            [0, 1, 'Tanjung Priok',     'Hamburg Port',   'Rubber products',   9900,  41,   2, 26, 'CANCELLED',  0],
        ];

        $sampleItems = [
            ['Palletized cartons', 40], ['Wooden crates', 12], ['Sealed drums', 20],
            ['Loose bags', 120], ['Boxed units', 60],
        ];

        foreach ($rows as $i => [$c, $k, $from, $to, $cargo, $kg, $m3, $depDays, $duration, $status, $lateHours]) {
            $departure = now()->addDays($depDays)->startOfHour();
            $expected = $departure->copy()->addDays($duration);

            $shipment = new Shipment([
                'customer_id' => $customers[$c % $customers->count()]->id,
                'carrier_id' => $carriers[$k % $carriers->count()]->id,
                'origin_id' => $loc($from)->id,
                'destination_id' => $loc($to)->id,
                'cargo_type' => $cargo,
                'weight_kg' => $kg,
                'volume_cbm' => $m3,
                'expected_departure_at' => $departure,
                'expected_arrival_at' => $expected,
                'estimated_arrival_at' => $expected->copy()->addHours($lateHours),
            ]);

            $shipment->forceFill([
                'shipment_number' => $service->generateNumber(),
                'status' => $status,
                'created_by' => $operator?->id,
                'delivered_at' => $status === 'DELIVERED' ? $expected->copy()->addHours($lateHours) : null,
                'cancelled_at' => $status === 'CANCELLED' ? now()->subDay() : null,
            ])->save();

            // 1 sampai 3 item per shipment
            foreach (array_slice($sampleItems, $i % 3, 1 + ($i % 3)) as [$desc, $qty]) {
                $shipment->items()->create([
                    'description' => $desc.' ('.$cargo.')',
                    'quantity' => $qty,
                    'weight_kg' => round($kg / (1 + ($i % 3)), 2),
                ]);
            }
        }
    }
}
