<?php
/*
 * ============================================================
 * BUAT APA : Pembantu tes: membuat data form shipment yang VALID.
 *            Tiap tes tinggal mengubah satu bagian untuk menguji satu aturan.
 * ============================================================
 */

namespace Tests\Concerns;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\User;

trait BuildsShipments
{
    protected function validPayload(array $overrides = []): array
    {
        $departure = now()->addDays(3)->startOfHour();

        return array_merge([
            'customer_id' => User::factory()->customer()->create()->id,
            'carrier_id' => Carrier::factory()->create()->id,
            'origin_id' => Location::factory()->create()->id,
            'destination_id' => Location::factory()->create()->id,
            'cargo_type' => 'Electronics',
            'weight_kg' => 1200.5,
            'volume_cbm' => 14.25,
            'expected_departure_at' => $departure->format('Y-m-d\TH:i'),
            'expected_arrival_at' => $departure->copy()->addDays(20)->format('Y-m-d\TH:i'),
            'estimated_arrival_at' => '',
            'items' => [
                ['description' => 'Boxed units', 'quantity' => 10, 'weight_kg' => 600],
                ['description' => 'Wooden crates', 'quantity' => 2, 'weight_kg' => ''],
            ],
        ], $overrides);
    }
}
