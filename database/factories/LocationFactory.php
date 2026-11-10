<?php
/*
 * ============================================================
 * BUAT APA : Pabrik data lokasi palsu untuk TES.
 * ============================================================
 */

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' Port',
            'city' => fake()->city(),
            'country' => fake()->country(),
            'latitude' => fake()->latitude(-60, 60),
            'longitude' => fake()->longitude(),
        ];
    }
}
