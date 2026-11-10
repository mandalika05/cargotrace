<?php
/*
 * ============================================================
 * BUAT APA : Pabrik data carrier palsu untuk TES (bukan untuk demo).
 * ============================================================
 */

namespace Database\Factories;

use App\Models\Carrier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Carrier> */
class CarrierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'contact_email' => fake()->safeEmail(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
