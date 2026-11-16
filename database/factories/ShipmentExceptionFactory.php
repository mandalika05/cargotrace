<?php
/*
 * ============================================================
 * BUAT APA : Pabrik exception palsu untuk TES.
 * STATE    : ->investigating() / ->resolved() / ->high()
 * ============================================================
 */

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShipmentException> */
class ShipmentExceptionFactory extends Factory
{
    // Data bawaan exception palsu untuk tes: jenis acak, MEDIUM, OPEN, terdeteksi 3 jam lalu.
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'type' => fake()->randomElement(ShipmentException::TYPES),
            'severity' => ShipmentException::SEVERITY_MEDIUM,
            'status' => ShipmentException::STATUS_OPEN,
            'description' => fake()->sentence(),
            'detected_at' => now()->subHours(3),
            'reported_by' => User::factory()->operator(),
        ];
    }

    // Variasi: keparahan HIGH. Contoh: ShipmentException::factory()->high()->create()
    public function high(): static
    {
        return $this->state(['severity' => ShipmentException::SEVERITY_HIGH]);
    }

    // Variasi: status INVESTIGATING (sedang diselidiki).
    public function investigating(): static
    {
        return $this->state(['status' => ShipmentException::STATUS_INVESTIGATING]);
    }

    // Variasi: sudah RESOLVED, lengkap dengan waktu dan catatan penyelesaian.
    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => ShipmentException::STATUS_RESOLVED,
            'resolved_at' => now()->subHour(),
            'resolution_note' => 'Resolved in tests.',
        ]);
    }
}
