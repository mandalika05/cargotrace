<?php
/*
 * ============================================================
 * BUAT APA : Pabrik shipment palsu untuk TES.
 * STATE    : ->delivered() / ->cancelled() untuk shipment terminal.
 * CATATAN  : factory boleh mengisi status walau status tidak ada di $fillable.
 * ============================================================
 */

namespace Database\Factories;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Shipment> */
class ShipmentFactory extends Factory
{
    public function definition(): array
    {
        $departure = Carbon::now()->addDays(fake()->numberBetween(1, 5))->startOfHour();
        $arrival = $departure->copy()->addDays(fake()->numberBetween(10, 25));

        return [
            'shipment_number' => fake()->unique()->bothify('CT-########-????'),
            'customer_id' => User::factory()->customer(),
            'carrier_id' => Carrier::factory(),
            'origin_id' => Location::factory(),
            'destination_id' => Location::factory(),
            'created_by' => User::factory()->operator(),
            'cargo_type' => fake()->randomElement(['Electronics', 'Textiles', 'Machinery parts', 'Coffee beans']),
            'weight_kg' => fake()->randomFloat(2, 50, 20000),
            'volume_cbm' => fake()->randomFloat(2, 1, 60),
            'expected_departure_at' => $departure,
            'expected_arrival_at' => $arrival,
            'estimated_arrival_at' => $arrival,
            'status' => Shipment::STATUS_CREATED,
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn (array $a) => [
            'status' => Shipment::STATUS_DELIVERED,
            'delivered_at' => $a['expected_arrival_at'],
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => Shipment::STATUS_CANCELLED, 'cancelled_at' => now()]);
    }
}
