<?php
/*
 * ============================================================
 * BUAT APA : Menguji REST API shipment, tracking, delay/ETA, exception, antrean, dashboard
 *            dan pengaturan. Fokus: hak akses API SAMA dengan web (admin / operator /
 *            customer), validasi, dan bentuk JSON.
 * ============================================================
 */

namespace Tests\Feature\Api;

use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsShipments;
use Tests\TestCase;

class ApiShipmentTest extends TestCase
{
    use BuildsShipments, RefreshDatabase;

    private function as(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    private function running(array $extra = []): Shipment
    {
        $expected = now()->addDays(4)->startOfMinute();

        return Shipment::factory()->create(array_merge([
            'status' => 'IN_TRANSIT',
            'expected_departure_at' => now()->subDays(10),
            'expected_arrival_at' => $expected,
            'estimated_arrival_at' => $expected,
        ], $extra));
    }

    // ---------- Shipment ----------

    public function test_list_is_paginated_and_customers_only_see_their_own(): void
    {
        $mine = $this->running();
        $this->running();
        $this->running();

        $this->as(User::factory()->operator()->create())->getJson('/api/v1/shipments?per_page=2')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);

        $this->as($mine->customer)->getJson('/api/v1/shipments')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_list_supports_search_and_status_filter(): void
    {
        $a = $this->running();
        $this->running(['status' => 'BOOKED']);

        $op = User::factory()->operator()->create();

        $this->as($op)->getJson('/api/v1/shipments?q='.$a->shipment_number)->assertOk()->assertJsonCount(1, 'data');
        $this->as($op)->getJson('/api/v1/shipments?status=BOOKED')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'BOOKED');
    }

    public function test_show_has_the_expected_shape_including_delay(): void
    {
        $s = $this->running();

        $this->as(User::factory()->operator()->create())->getJson('/api/v1/shipments/'.$s->id)
            ->assertOk()
            ->assertJsonPath('data.shipment_number', $s->shipment_number)
            ->assertJsonPath('data.delay.state', 'ON_TIME')
            ->assertJsonStructure(['data' => ['id', 'status', 'progress_percent', 'carrier', 'origin', 'destination', 'items', 'delay' => ['reason', 'thresholds']]]);
    }

    public function test_a_customer_cannot_read_someone_elses_shipment(): void
    {
        $s = $this->running();

        $this->as(User::factory()->customer()->create())->getJson('/api/v1/shipments/'.$s->id)->assertForbidden();
        $this->as(User::factory()->customer()->create())->getJson('/api/v1/shipments/'.$s->id.'/tracking')->assertForbidden();
        $this->as(User::factory()->customer()->create())->getJson('/api/v1/shipments/'.$s->id.'/exceptions')->assertForbidden();
        $this->as(User::factory()->customer()->create())->getJson('/api/v1/shipments/'.$s->id.'/delay')->assertForbidden();
    }

    public function test_operator_creates_a_shipment_and_status_cannot_be_forced(): void
    {
        $operator = User::factory()->operator()->create();

        $this->as($operator)->postJson('/api/v1/shipments', $this->validPayload(['status' => 'DELIVERED']))
            ->assertCreated()
            ->assertJsonPath('data.status', 'CREATED');

        $this->assertDatabaseCount('shipments', 1);
    }

    public function test_create_validates_and_customers_cannot_create(): void
    {
        $this->as(User::factory()->operator()->create())->postJson('/api/v1/shipments', [])
            ->assertStatus(422)->assertJsonValidationErrors(['customer_id', 'carrier_id', 'items']);

        $this->as(User::factory()->customer()->create())->postJson('/api/v1/shipments', $this->validPayload())->assertForbidden();
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_update_and_delete_follow_the_roles(): void
    {
        $s = $this->running();
        $payload = $this->validPayload(['cargo_type' => 'Frozen goods']);
        $payload['customer_id'] = $s->customer_id;
        $payload['carrier_id'] = $s->carrier_id;
        $payload['origin_id'] = $s->origin_id;
        $payload['destination_id'] = $s->destination_id;

        $operator = User::factory()->operator()->create();

        $this->as($operator)->putJson('/api/v1/shipments/'.$s->id, $payload)->assertOk()->assertJsonPath('data.cargo_type', 'Frozen goods');
        $this->as($operator)->deleteJson('/api/v1/shipments/'.$s->id)->assertForbidden();
        $this->as(User::factory()->admin()->create())->deleteJson('/api/v1/shipments/'.$s->id)->assertNoContent();
        $this->assertSoftDeleted('shipments', ['id' => $s->id]);
    }

    public function test_a_finished_shipment_cannot_be_updated(): void
    {
        $s = Shipment::factory()->delivered()->create();

        $this->as(User::factory()->operator()->create())->putJson('/api/v1/shipments/'.$s->id, $this->validPayload())->assertForbidden();
    }

    // ---------- Tracking ----------

    public function test_tracking_timeline_and_adding_an_event(): void
    {
        $s = $this->running();
        $operator = User::factory()->operator()->create();

        $this->as($operator)->postJson('/api/v1/shipments/'.$s->id.'/tracking', [
            'event_type' => 'LOCATION_UPDATE',
            'occurred_at' => now()->subHour()->toIso8601String(),
            'location_name' => 'Colombo, Sri Lanka',
            'latitude' => 6.9271, 'longitude' => 79.8612,
        ])->assertCreated()->assertJsonPath('data.event_type', 'LOCATION_UPDATE');

        $this->as($s->customer)->getJson('/api/v1/shipments/'.$s->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('meta.current_location.name', 'Colombo, Sri Lanka')
            ->assertJsonCount(1, 'data');
    }

    public function test_invalid_status_jumps_and_customers_cannot_add_events(): void
    {
        $s = $this->running(['status' => 'BOOKED']);

        $this->as(User::factory()->operator()->create())->postJson('/api/v1/shipments/'.$s->id.'/tracking', [
            'event_type' => 'DELIVERED', 'occurred_at' => now()->subHour()->toIso8601String(),
        ])->assertStatus(422);

        $this->as($s->customer)->postJson('/api/v1/shipments/'.$s->id.'/tracking', [
            'event_type' => 'PICKED_UP', 'occurred_at' => now()->subHour()->toIso8601String(),
        ])->assertForbidden();
    }

    public function test_only_admin_can_reopen_and_a_reason_is_required(): void
    {
        $s = Shipment::factory()->delivered()->create();

        $this->as(User::factory()->operator()->create())->postJson('/api/v1/shipments/'.$s->id.'/reopen', ['reason' => 'Customer disputes the delivery'])->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->as($admin)->postJson('/api/v1/shipments/'.$s->id.'/reopen', ['reason' => 'short'])->assertStatus(422);
        $this->as($admin)->postJson('/api/v1/shipments/'.$s->id.'/reopen', ['reason' => 'Customer disputes the delivery'])
            ->assertOk()->assertJsonPath('data.event_type', 'REOPENED');
    }

    // ---------- Delay / ETA ----------

    public function test_delay_endpoint_explains_itself_and_eta_can_be_updated_by_staff(): void
    {
        $s = $this->running();
        $operator = User::factory()->operator()->create();
        $newEta = $s->expected_arrival_at->copy()->addHours(30);

        $this->as($operator)->patchJson('/api/v1/shipments/'.$s->id.'/eta', [
            'estimated_arrival_at' => $newEta->toIso8601String(),
            'reason' => 'Storm near the Suez Canal',
        ])->assertOk()->assertJsonPath('delay.severity', 'HIGH')->assertJsonPath('event.event_type', 'ETA_UPDATED');

        $this->as($s->customer)->getJson('/api/v1/shipments/'.$s->id.'/delay')
            ->assertOk()->assertJsonPath('data.is_delayed', true)->assertJsonPath('data.delay_minutes', 1800)
            ->assertJsonStructure(['data' => ['reason', 'thresholds' => ['low_max_hours', 'medium_max_hours']]]);
    }

    public function test_eta_update_is_forbidden_for_customers_and_needs_a_reason(): void
    {
        $s = $this->running();

        $this->as($s->customer)->patchJson('/api/v1/shipments/'.$s->id.'/eta', ['estimated_arrival_at' => now()->addDays(9)->toIso8601String(), 'reason' => 'Because'])->assertForbidden();
        $this->as(User::factory()->operator()->create())->patchJson('/api/v1/shipments/'.$s->id.'/eta', ['estimated_arrival_at' => now()->addDays(9)->toIso8601String()])
            ->assertStatus(422)->assertJsonValidationErrors('reason');
    }

    // ---------- Exceptions ----------

    public function test_exception_lifecycle_over_the_api(): void
    {
        $s = $this->running();
        $operator = User::factory()->operator()->create();

        $id = $this->as($operator)->postJson('/api/v1/shipments/'.$s->id.'/exceptions', [
            'type' => 'CUSTOMS_HOLD', 'severity' => 'HIGH', 'description' => 'Extra inspection',
        ])->assertCreated()->assertJsonPath('data.status', 'OPEN')->json('data.id');

        $this->as($operator)->patchJson('/api/v1/exceptions/'.$id, ['status' => 'INVESTIGATING'])
            ->assertOk()->assertJsonPath('data.status', 'INVESTIGATING');
        $this->as($operator)->patchJson('/api/v1/exceptions/'.$id, ['status' => 'RESOLVED'])->assertStatus(422);
        $this->as($operator)->patchJson('/api/v1/exceptions/'.$id, ['status' => 'RESOLVED', 'note' => 'Cleared by customs'])
            ->assertOk()->assertJsonPath('data.status', 'RESOLVED');
        $this->as($operator)->patchJson('/api/v1/exceptions/'.$id, ['status' => 'INVESTIGATING'])->assertForbidden();
    }

    public function test_customers_read_their_exceptions_but_cannot_change_anything(): void
    {
        $s = $this->running();
        $mine = ShipmentException::factory()->create(['shipment_id' => $s->id]);
        ShipmentException::factory()->create();

        $this->as($s->customer)->getJson('/api/v1/exceptions')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->as($s->customer)->postJson('/api/v1/shipments/'.$s->id.'/exceptions', ['type' => 'DAMAGED', 'severity' => 'LOW'])->assertForbidden();
        $this->as($s->customer)->patchJson('/api/v1/exceptions/'.$mine->id, ['status' => 'INVESTIGATING'])->assertForbidden();
    }

    // ---------- Antrean, dashboard, lookup, settings ----------

    public function test_attention_queue_is_staff_only_and_sorted(): void
    {
        $low = $this->running(['estimated_arrival_at' => now()->addDays(4)->startOfMinute()->addHours(2)]);
        $high = $this->running(['estimated_arrival_at' => now()->addDays(4)->startOfMinute()->addHours(40)]);

        $this->as($low->customer)->getJson('/api/v1/attention')->assertForbidden();

        $this->as(User::factory()->operator()->create())->getJson('/api/v1/attention')
            ->assertOk()
            ->assertJsonPath('data.0.shipment_id', $high->id)
            ->assertJsonPath('data.0.priority', 'HIGH')
            ->assertJsonPath('data.1.shipment_id', $low->id);
    }

    public function test_dashboard_counts_are_scoped_by_role(): void
    {
        $mine = $this->running();
        $this->running();
        ShipmentException::factory()->create(['shipment_id' => $mine->id]);

        $this->as(User::factory()->operator()->create())->getJson('/api/v1/dashboard')
            ->assertOk()->assertJsonPath('data.total', 2)->assertJsonPath('data.by_status.IN_TRANSIT', 2);

        $this->as($mine->customer)->getJson('/api/v1/dashboard')
            ->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.exceptions.unresolved', 1);
    }

    public function test_lookups_are_staff_only(): void
    {
        $this->as(User::factory()->customer()->create())->getJson('/api/v1/carriers')->assertForbidden();
        $this->as(User::factory()->customer()->create())->getJson('/api/v1/locations')->assertForbidden();
        $this->as(User::factory()->operator()->create())->getJson('/api/v1/carriers')->assertOk();
        $this->as(User::factory()->operator()->create())->getJson('/api/v1/locations')->assertOk();
    }

    public function test_only_admin_changes_the_delay_settings(): void
    {
        $payload = ['delay_low_max_hours' => 4, 'delay_medium_max_hours' => 12];

        $this->as(User::factory()->operator()->create())->putJson('/api/v1/settings/delay', $payload)->assertForbidden();
        $this->as(User::factory()->admin()->create())->putJson('/api/v1/settings/delay', ['delay_low_max_hours' => 10, 'delay_medium_max_hours' => 10])->assertStatus(422);
        $this->as(User::factory()->admin()->create())->putJson('/api/v1/settings/delay', $payload)
            ->assertOk()->assertJsonPath('data.low_max_hours', 4)->assertJsonPath('data.medium_max_hours', 12);
    }
}
