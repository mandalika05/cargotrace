<?php
/*
 * ============================================================
 * BUAT APA : Exception demo, KONSISTEN dengan data shipment yang sudah ada
 *            (syarat brief: data sintetis tetapi realistis).
 *   shipment #3  (DELIVERED, telat 9 jam)  -> MISROUTED, sudah RESOLVED
 *   shipment #5  (IN_TRANSIT, telat 5 jam) -> DOCUMENTATION_ISSUE, OPEN, LOW
 *   shipment #6  (IN_TRANSIT, telat 15 jam)-> PORT_CONGESTION, INVESTIGATING, MEDIUM
 *   shipment #7  (IN_TRANSIT, telat 30 jam)-> WEATHER_DELAY, OPEN, HIGH
 *                                           + CUSTOMS_HOLD, INVESTIGATING, HIGH
 * Setiap exception punya event penanda di timeline (sama seperti yang dibuat
 * ExceptionService), dengan waktu yang tidak di masa depan dan tidak sebelum berangkat.
 * ============================================================
 */

namespace Database\Seeders;

use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ExceptionSeeder extends Seeder
{
    // Membuat 5 exception demo (berbagai jenis/status) pada shipment yang ada, lengkap dengan penanda di timeline.
    // Waktu deteksi dijaga: tidak sebelum shipment berangkat dan tidak di masa depan. Dilewati kalau sudah ada exception.
    public function run(): void
    {
        if (ShipmentException::exists()) {
            return; // jangan dobel kalau seeder dijalankan lagi
        }

        $operatorId = User::where('email', 'operator@cargotrace.test')->value('id');
        $shipments = Shipment::orderBy('id')->get();

        // [indeks shipment (mulai 0), jenis, severity, status, jam lalu terdeteksi, jam lalu selesai|null, deskripsi]
        $rows = [
            [2, ShipmentException::TYPE_MISROUTED, 'MEDIUM', 'RESOLVED', 24 * 20, 24 * 18,
                'Containers were routed to the wrong terminal at transshipment.', 'Re-routed to the correct terminal; no cargo damage.'],
            [4, ShipmentException::TYPE_DOCUMENTATION_ISSUE, 'LOW', 'OPEN', 20, null,
                'Commercial invoice is missing a signature.', null],
            [5, ShipmentException::TYPE_PORT_CONGESTION, 'MEDIUM', 'INVESTIGATING', 30, null,
                'Berth waiting time at the transshipment port is longer than planned.', null],
            [6, ShipmentException::TYPE_WEATHER_DELAY, 'HIGH', 'OPEN', 14, null,
                'Severe storm on the route; vessel slowed down for safety.', null],
            [6, ShipmentException::TYPE_CUSTOMS_HOLD, 'HIGH', 'INVESTIGATING', 40, null,
                'Customs requested an extra inspection of the declared goods.', null],
        ];

        foreach ($rows as [$index, $type, $severity, $status, $hoursAgo, $resolvedHoursAgo, $description, $note]) {
            $shipment = $shipments[$index] ?? null;
            if (! $shipment) {
                continue;
            }

            // Tidak boleh sebelum shipment berangkat
            $detectedAt = now()->subHours($hoursAgo)->max($shipment->expected_departure_at->copy()->addHour());
            if ($detectedAt->isFuture()) {
                $detectedAt = now()->subHour();
            }
            $resolvedAt = $resolvedHoursAgo !== null ? now()->subHours($resolvedHoursAgo)->max($detectedAt->copy()->addHour()) : null;

            $exception = new ShipmentException(['type' => $type, 'severity' => $severity, 'description' => $description]);
            $exception->forceFill([
                'shipment_id' => $shipment->id,
                'status' => $status,
                'detected_at' => $detectedAt,
                'resolved_at' => $resolvedAt,
                'resolution_note' => $note,
                'reported_by' => $operatorId,
                'resolved_by' => $resolvedAt ? $operatorId : null,
            ])->save();

            $label = ShipmentException::typeLabel($type);
            $this->marker($shipment, TrackingEvent::TYPE_EXCEPTION, $detectedAt, $operatorId, "Exception reported: {$label} ({$severity}). {$description}");

            if ($status === 'INVESTIGATING') {
                $this->marker($shipment, TrackingEvent::TYPE_EXCEPTION_UPDATE, $detectedAt->copy()->addHour(), $operatorId, "{$label} is now being investigated.");
            }
            if ($resolvedAt) {
                $this->marker($shipment, TrackingEvent::TYPE_EXCEPTION_RESOLVED, $resolvedAt, $operatorId, "{$label} resolved. {$note}");
            }
        }
    }

    // Menulis satu penanda (event) ke timeline shipment untuk data demo.
    private function marker(Shipment $shipment, string $type, Carbon $at, ?int $userId, string $description): void
    {
        $event = new TrackingEvent(['event_type' => $type, 'occurred_at' => $at, 'description' => $description]);
        $event->forceFill(['shipment_id' => $shipment->id, 'created_by' => $userId, 'created_at' => $at])->save();
    }
}
