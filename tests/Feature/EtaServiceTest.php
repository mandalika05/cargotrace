<?php
/*
 * ============================================================
 * BUAT APA : Menguji ETA dan DELAY (syarat brief: "ETA/delay rule").
 * ISI TES  : tepat waktu, LOW/MEDIUM/HIGH, shipment delivered terlambat, dibatalkan,
 *            "overdue", ambang yang bisa diubah (settings), alasan memuat nilai,
 *            dan pembaruan ETA oleh staf (dengan alasan, tercatat di timeline).
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\EtaService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EtaServiceTest extends TestCase
{
    use RefreshDatabase;

    private EtaService $eta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eta = app(EtaService::class);
    }

    /** Shipment IN_TRANSIT yang jadwal tibanya 5 hari lagi, ETA digeser $lateMinutes menit. */
    private function shipmentLate(int $lateMinutes, array $extra = []): Shipment
    {
        $expected = now()->addDays(5)->startOfMinute();

        return Shipment::factory()->create(array_merge([
            'status' => 'IN_TRANSIT',
            'expected_departure_at' => now()->subDays(10),
            'expected_arrival_at' => $expected,
            'estimated_arrival_at' => $expected->copy()->addMinutes($lateMinutes),
        ], $extra));
    }

    public function test_on_time_shipment_is_not_delayed(): void
    {
        $a = $this->eta->assess($this->shipmentLate(0));

        $this->assertFalse($a->isDelayed());
        $this->assertNull($a->severity);
        $this->assertSame('On time', $a->label());
        $this->assertSame('chip-delivered', $a->chipClass());
    }

    public function test_earlier_than_expected_is_on_time(): void
    {
        $a = $this->eta->assess($this->shipmentLate(-120));

        $this->assertFalse($a->isDelayed());
        $this->assertSame(-120, $a->delayMinutes);
        $this->assertStringContainsString('before the expected arrival', $a->reason);
    }

    public function test_low_medium_and_high_delays(): void
    {
        $this->assertSame('LOW', $this->eta->assess($this->shipmentLate(5 * 60))->severity);
        $this->assertSame('LOW', $this->eta->assess($this->shipmentLate(6 * 60))->severity);
        $this->assertSame('MEDIUM', $this->eta->assess($this->shipmentLate(6 * 60 + 1))->severity);
        $this->assertSame('MEDIUM', $this->eta->assess($this->shipmentLate(24 * 60))->severity);
        $this->assertSame('HIGH', $this->eta->assess($this->shipmentLate(24 * 60 + 1))->severity);
    }

    public function test_the_reason_shows_the_values_that_were_used(): void
    {
        $a = $this->eta->assess($this->shipmentLate(15 * 60));

        $this->assertTrue($a->isDelayed());
        $this->assertSame(900, $a->delayMinutes);
        $this->assertSame('Delayed - Medium', $a->label());
        $this->assertStringContainsString('15h 00m', $a->reason);
        $this->assertStringContainsString('up to 6h = LOW', $a->reason);
        $this->assertStringContainsString('up to 24h = MEDIUM', $a->reason);
    }

    public function test_thresholds_follow_the_settings_table(): void
    {
        Setting::putValue('delay_low_max_hours', 2);
        Setting::putValue('delay_medium_max_hours', 10);

        $this->assertSame(['low_max_hours' => 2, 'medium_max_hours' => 10], $this->eta->thresholds());
        $this->assertSame('MEDIUM', $this->eta->assess($this->shipmentLate(3 * 60))->severity);
        $this->assertSame('HIGH', $this->eta->assess($this->shipmentLate(11 * 60))->severity);
    }

    public function test_nonsense_thresholds_fall_back_to_the_defaults(): void
    {
        Setting::putValue('delay_low_max_hours', 30);
        Setting::putValue('delay_medium_max_hours', 12); // lebih kecil dari low

        $this->assertSame(['low_max_hours' => 6, 'medium_max_hours' => 24], $this->eta->thresholds());
    }

    public function test_a_delivered_shipment_is_judged_by_its_real_arrival_time(): void
    {
        $expected = now()->subDays(3)->startOfMinute();
        $shipment = Shipment::factory()->delivered()->create([
            'expected_departure_at' => now()->subDays(20),
            'expected_arrival_at' => $expected,
            'delivered_at' => $expected->copy()->addHours(9),
            'estimated_arrival_at' => $expected->copy()->addHours(9),
        ]);

        $a = $this->eta->assess($shipment);

        $this->assertSame('MEDIUM', $a->severity);
        $this->assertStringContainsString('Delivery time', $a->reason);
    }

    public function test_a_delivered_shipment_on_time_is_on_time(): void
    {
        $expected = now()->subDays(3)->startOfMinute();
        $shipment = Shipment::factory()->delivered()->create([
            'expected_departure_at' => now()->subDays(20),
            'expected_arrival_at' => $expected,
            'delivered_at' => $expected->copy()->subHours(2),
            'estimated_arrival_at' => $expected->copy()->subHours(2),
        ]);

        $this->assertFalse($this->eta->assess($shipment)->isDelayed());
    }

    public function test_a_cancelled_shipment_has_no_delay(): void
    {
        $shipment = Shipment::factory()->cancelled()->create([
            'expected_arrival_at' => now()->subDays(2),
            'estimated_arrival_at' => now()->subDay(),
        ]);

        $a = $this->eta->assess($shipment);

        $this->assertSame('NOT_APPLICABLE', $a->state);
        $this->assertFalse($a->isDelayed());
    }

    public function test_overdue_shipment_is_delayed_even_if_the_eta_was_never_updated(): void
    {
        $expected = now()->subHours(8)->startOfMinute();
        $shipment = Shipment::factory()->create([
            'status' => 'IN_TRANSIT',
            'expected_departure_at' => now()->subDays(20),
            'expected_arrival_at' => $expected,
            'estimated_arrival_at' => $expected, // ETA belum digeser
        ]);

        $a = $this->eta->assess($shipment, now());

        $this->assertTrue($a->overdue);
        $this->assertSame('MEDIUM', $a->severity); // sekitar 8 jam
        $this->assertStringContainsString('has passed', $a->reason);
    }

    public function test_the_shipment_model_exposes_the_assessment(): void
    {
        $this->assertSame('HIGH', $this->shipmentLate(30 * 60)->delay()->severity);
    }

    // ---------- updateEta ----------

    public function test_staff_can_update_the_eta_with_a_reason_and_it_is_logged(): void
    {
        $shipment = $this->shipmentLate(0);
        $operator = User::factory()->operator()->create();
        $newEta = $shipment->expected_arrival_at->copy()->addHours(30);

        $event = $this->eta->updateEta($shipment, $newEta, 'Storm near the Suez Canal', $operator);

        $this->assertSame(TrackingEvent::TYPE_ETA_UPDATED, $event->event_type);
        $this->assertTrue($shipment->fresh()->estimated_arrival_at->equalTo($newEta));
        $this->assertStringContainsString('Storm near the Suez Canal', $event->description);
        $this->assertStringContainsString('HIGH', $event->description);
        $this->assertDatabaseHas('tracking_events', ['shipment_id' => $shipment->id, 'event_type' => 'ETA_UPDATED', 'created_by' => $operator->id]);
    }

    public function test_a_customer_cannot_update_the_eta(): void
    {
        $shipment = $this->shipmentLate(0);

        $this->expectException(AuthorizationException::class);
        $this->eta->updateEta($shipment, now()->addDays(9), 'I want it later', $shipment->customer);
    }

    public function test_eta_update_needs_a_reason_a_real_change_and_a_running_shipment(): void
    {
        $operator = User::factory()->operator()->create();
        $shipment = $this->shipmentLate(0);

        $try = function (callable $fn, string $field) {
            try {
                $fn();
                $this->fail("Expected a validation error on {$field}.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($field, $e->errors());
            }
        };

        $try(fn () => $this->eta->updateEta($shipment, $shipment->expected_arrival_at->copy()->addDay(), '  ', $operator), 'reason');
        $try(fn () => $this->eta->updateEta($shipment, $shipment->expected_arrival_at, 'Same value again', $operator), 'estimated_arrival_at');
        $try(fn () => $this->eta->updateEta($shipment, now()->subDays(30), 'Before departure', $operator), 'estimated_arrival_at');

        $delivered = Shipment::factory()->delivered()->create();
        $try(fn () => $this->eta->updateEta($delivered, now()->addDays(3), 'Too late for this', $operator), 'estimated_arrival_at');

        $this->assertDatabaseCount('tracking_events', 0);
    }

    public function test_an_eta_marker_does_not_block_the_next_physical_event(): void
    {
        $shipment = $this->shipmentLate(0);
        $operator = User::factory()->operator()->create();

        $this->eta->updateEta($shipment, $shipment->expected_arrival_at->copy()->addHours(5), 'Port delay', $operator);

        // Event perjalanan dengan waktu lebih awal dari penanda ETA tetap boleh
        $this->actingAs($operator)->post(route('shipments.tracking.store', $shipment), [
            'event_type' => 'LOCATION_UPDATE',
            'occurred_at' => now()->subHours(2)->format('Y-m-d\TH:i'),
            'location_name' => 'Colombo, Sri Lanka',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tracking_events', ['shipment_id' => $shipment->id, 'location_name' => 'Colombo, Sri Lanka']);
    }
}
