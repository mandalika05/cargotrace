<?php
/*
 * ============================================================
 * BUAT APA : Menguji tracking event dan transisi status (syarat brief:
 *            "Tracking event dan invalid status transition").
 * ISI TES  : alur lengkap, transisi tidak sah, urutan waktu, waktu masa depan,
 *            event tidak bisa diubah/dihapus, hak akses, lokasi terkini,
 *            prosedur reopen Admin.
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = User::factory()->operator()->create();
    }

    private function at(int $hoursAgo): string
    {
        return now()->subHours($hoursAgo)->format('Y-m-d\TH:i');
    }

    private function sendEvent(Shipment $shipment, string $type, int $hoursAgo, array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->operator)->post(
            route('shipments.tracking.store', $shipment),
            array_merge(['event_type' => $type, 'occurred_at' => $this->at($hoursAgo)], $extra)
        );
    }

    public function test_a_valid_status_event_is_saved_and_changes_the_status(): void
    {
        $shipment = Shipment::factory()->create();

        $this->sendEvent($shipment, 'BOOKED', 5, ['description' => 'Booked with carrier'])
            ->assertRedirect(route('shipments.show', $shipment).'#timeline');

        $this->assertSame('BOOKED', $shipment->fresh()->status);
        $this->assertDatabaseHas('tracking_events', [
            'shipment_id' => $shipment->id, 'event_type' => 'BOOKED', 'created_by' => $this->operator->id,
        ]);
    }

    public function test_full_lifecycle_reaches_delivered_and_sets_delivery_time(): void
    {
        $shipment = Shipment::factory()->create();

        $this->sendEvent($shipment, 'BOOKED', 10);
        $this->sendEvent($shipment, 'PICKED_UP', 8);
        $this->sendEvent($shipment, 'IN_TRANSIT', 6);
        $this->sendEvent($shipment, 'DELIVERED', 1);

        $shipment->refresh();
        $this->assertSame('DELIVERED', $shipment->status);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertTrue($shipment->estimated_arrival_at->equalTo($shipment->delivered_at));
        $this->assertSame(4, $shipment->trackingEvents()->count());
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $created = Shipment::factory()->create();
        $this->sendEvent($created, 'IN_TRANSIT', 2)->assertSessionHasErrors('event_type');
        $this->sendEvent($created, 'DELIVERED', 2)->assertSessionHasErrors('event_type');
        $this->assertSame('CREATED', $created->fresh()->status);

        $booked = Shipment::factory()->create(['status' => 'BOOKED']);
        $this->sendEvent($booked, 'DELIVERED', 2)->assertSessionHasErrors('event_type');
        $this->assertSame('BOOKED', $booked->fresh()->status);

        $transit = Shipment::factory()->create(['status' => 'IN_TRANSIT']);
        $this->sendEvent($transit, 'CANCELLED', 2)->assertSessionHasErrors('event_type');
        $this->sendEvent($transit, 'BOOKED', 2)->assertSessionHasErrors('event_type');
        $this->assertDatabaseCount('tracking_events', 0);
    }

    public function test_cancel_is_allowed_from_booked(): void
    {
        $shipment = Shipment::factory()->create(['status' => 'BOOKED']);

        $this->sendEvent($shipment, 'CANCELLED', 1);

        $shipment->refresh();
        $this->assertSame('CANCELLED', $shipment->status);
        $this->assertNotNull($shipment->cancelled_at);
    }

    public function test_terminal_shipments_accept_no_more_events(): void
    {
        $shipment = Shipment::factory()->delivered()->create();

        // Policy: staf tidak boleh mengubah shipment terminal -> 403
        $this->sendEvent($shipment, 'LOCATION_UPDATE', 1, ['location_name' => 'Somewhere'])->assertForbidden();
        $this->assertDatabaseCount('tracking_events', 0);
    }

    public function test_event_time_cannot_be_in_the_future(): void
    {
        $shipment = Shipment::factory()->create();

        $this->actingAs($this->operator)->post(route('shipments.tracking.store', $shipment), [
            'event_type' => 'BOOKED',
            'occurred_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('occurred_at');

        $this->assertDatabaseCount('tracking_events', 0);
    }

    public function test_event_time_cannot_go_back_before_the_previous_event(): void
    {
        $shipment = Shipment::factory()->create();
        $this->sendEvent($shipment, 'BOOKED', 2);

        $this->sendEvent($shipment, 'PICKED_UP', 5)->assertSessionHasErrors('occurred_at');

        $this->assertSame('BOOKED', $shipment->fresh()->status);
        $this->assertDatabaseCount('tracking_events', 1);
    }

    public function test_location_update_needs_a_place_and_a_moving_shipment(): void
    {
        $moving = Shipment::factory()->create(['status' => 'IN_TRANSIT']);
        $this->sendEvent($moving, 'LOCATION_UPDATE', 2)->assertSessionHasErrors('location_name');

        $booked = Shipment::factory()->create(['status' => 'BOOKED']);
        $this->sendEvent($booked, 'LOCATION_UPDATE', 2, ['location_name' => 'Port X'])->assertSessionHasErrors('event_type');

        $this->sendEvent($moving, 'LOCATION_UPDATE', 2, ['location_name' => 'Suez Canal', 'latitude' => 30, 'longitude' => 32.5])
            ->assertSessionHasNoErrors();
        $this->assertSame('IN_TRANSIT', $moving->fresh()->status); // status tidak berubah
    }

    public function test_coordinates_are_copied_from_the_chosen_location(): void
    {
        $shipment = Shipment::factory()->create(['status' => 'PICKED_UP']);
        $port = Location::factory()->create(['name' => 'Test Port', 'latitude' => 1.25, 'longitude' => 103.5]);

        $this->sendEvent($shipment, 'IN_TRANSIT', 1, ['location_id' => $port->id]);

        $this->assertDatabaseHas('tracking_events', [
            'shipment_id' => $shipment->id, 'location_name' => 'Test Port', 'latitude' => 1.25, 'longitude' => 103.5,
        ]);
    }

    public function test_current_location_is_the_latest_event_with_a_place(): void
    {
        $shipment = Shipment::factory()->create(['status' => 'IN_TRANSIT']);

        $this->sendEvent($shipment, 'LOCATION_UPDATE', 6, ['location_name' => 'Colombo']);
        $this->sendEvent($shipment, 'LOCATION_UPDATE', 3, ['location_name' => 'Suez Canal']);
        $this->sendEvent($shipment, 'LOCATION_UPDATE', 1, ['location_name' => 'Gibraltar']);

        $this->assertSame('Gibraltar', $shipment->currentLocationEvent()->location_name);
    }

    public function test_events_are_history_and_cannot_be_edited_or_deleted(): void
    {
        $shipment = Shipment::factory()->create();
        $this->sendEvent($shipment, 'BOOKED', 3);
        $event = TrackingEvent::firstOrFail();

        try {
            $event->update(['description' => 'rewritten']);
            $this->fail('Editing an event should not be possible.');
        } catch (LogicException) {
        }

        try {
            $event->delete();
            $this->fail('Deleting an event should not be possible.');
        } catch (LogicException) {
        }

        $this->assertDatabaseHas('tracking_events', ['id' => $event->id]);
    }

    public function test_customer_cannot_add_events_but_can_see_the_timeline(): void
    {
        $shipment = Shipment::factory()->create();
        $this->sendEvent($shipment, 'BOOKED', 3, ['description' => 'Booking confirmed with carrier']);

        $customer = $shipment->customer;

        $this->sendEvent($shipment, 'PICKED_UP', 1, [], $customer)->assertForbidden();

        $this->actingAs($customer)->get(route('shipments.show', $shipment))
            ->assertOk()
            ->assertSee('Tracking timeline')
            ->assertSee('Booking confirmed with carrier')
            ->assertDontSee('Add tracking event');
    }

    public function test_other_customers_cannot_see_the_timeline(): void
    {
        $shipment = Shipment::factory()->create();

        $this->actingAs(User::factory()->customer()->create())
            ->get(route('shipments.show', $shipment))
            ->assertForbidden();
    }

    // ---------- Reopen (prosedur khusus Admin) ----------

    public function test_admin_can_reopen_a_delivered_shipment_with_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $shipment = Shipment::factory()->delivered()->create();

        $this->actingAs($admin)->post(route('shipments.reopen', $shipment), ['reason' => 'Delivery was recorded by mistake.'])
            ->assertRedirect(route('shipments.show', $shipment).'#timeline');

        $shipment->refresh();
        $this->assertSame('IN_TRANSIT', $shipment->status);
        $this->assertNull($shipment->delivered_at);
        $this->assertDatabaseHas('tracking_events', ['shipment_id' => $shipment->id, 'event_type' => 'REOPENED']);
    }

    public function test_reopening_a_cancelled_shipment_returns_it_to_created(): void
    {
        $shipment = Shipment::factory()->cancelled()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('shipments.reopen', $shipment), ['reason' => 'Customer changed their mind again.']);

        $this->assertSame('CREATED', $shipment->fresh()->status);
    }

    public function test_reopen_requires_a_reason_and_admin_and_a_terminal_shipment(): void
    {
        $admin = User::factory()->admin()->create();
        $delivered = Shipment::factory()->delivered()->create();

        $this->actingAs($admin)->post(route('shipments.reopen', $delivered), ['reason' => 'short'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->operator)->post(route('shipments.reopen', $delivered), ['reason' => 'A long enough reason here.'])
            ->assertForbidden();

        $running = Shipment::factory()->create(['status' => 'IN_TRANSIT']);
        $this->actingAs($admin)->post(route('shipments.reopen', $running), ['reason' => 'A long enough reason here.'])
            ->assertSessionHasErrors('reason');

        $this->assertSame('DELIVERED', $delivered->fresh()->status);
    }
}
