<?php
/*
 * ============================================================
 * BUAT APA : Memastikan DATA DEMO konsisten (syarat brief): status setiap
 *            shipment harus cocok dengan riwayat event-nya, urutan waktu benar,
 *            dan tidak ada event di masa depan.
 * CATATAN  : tes ini menjalankan DatabaseSeeder, jadi TrackingEventSeeder harus
 *            sudah didaftarkan di DatabaseSeeder (lihat LANGKAH-P10.md).
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\Shipment;
use App\Services\ShipmentStateMachine;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedDataConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_status_matches_the_event_history(): void
    {
        $this->seed(DatabaseSeeder::class);

        $machine = new ShipmentStateMachine();
        $this->assertGreaterThan(0, Shipment::count());

        foreach (Shipment::with('trackingEvents')->get() as $shipment) {
            $events = $shipment->trackingEvents->sortBy(fn ($e) => [$e->occurred_at->timestamp, $e->id])->values();

            $status = 'CREATED';
            $previous = null;

            foreach ($events as $event) {
                $this->assertTrue($event->occurred_at->lte(now()), "Future event on {$shipment->shipment_number}");

                if ($previous) {
                    $this->assertTrue($event->occurred_at->gte($previous), "Time goes backwards on {$shipment->shipment_number}");
                }
                $previous = $event->occurred_at;

                if ($event->isStatusChange()) {
                    $this->assertTrue(
                        $machine->canTransition($status, $event->event_type),
                        "Invalid {$status} -> {$event->event_type} on {$shipment->shipment_number}"
                    );
                    $status = $event->event_type;
                }
            }

            $this->assertSame($status, $shipment->status, "Status mismatch on {$shipment->shipment_number}");
        }
    }
}
