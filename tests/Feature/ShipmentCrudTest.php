<?php
/*
 * ============================================================
 * BUAT APA : Menguji CRUD shipment + validasi (syarat brief:
 *            "Shipment CRUD + validation + authorization").
 * ISI TES  : buat valid, ETA otomatis, aturan validasi (tanggal, origin=destination,
 *            tanpa item, customer salah), status tidak bisa dipaksa dari input,
 *            ubah + ganti item, search dan filter.
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShipments;
use Tests\TestCase;

class ShipmentCrudTest extends TestCase
{
    use BuildsShipments, RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = User::factory()->operator()->create();
    }

    public function test_operator_can_create_a_shipment_with_items(): void
    {
        $payload = $this->validPayload();

        $this->actingAs($this->operator)->post(route('shipments.store'), $payload)
            ->assertRedirect(route('shipments.show', Shipment::query()->latest('id')->firstOr(fn () => new Shipment(['id' => 1]))));

        $shipment = Shipment::firstOrFail();
        $this->assertSame('CREATED', $shipment->status);
        $this->assertSame($this->operator->id, (int) $shipment->created_by);
        $this->assertMatchesRegularExpression('/^CT-\d{8}-[A-Z0-9]{4}$/', $shipment->shipment_number);
        $this->assertDatabaseCount('shipment_items', 2);
    }

    public function test_eta_defaults_to_expected_arrival_when_left_blank(): void
    {
        $this->actingAs($this->operator)->post(route('shipments.store'), $this->validPayload());

        $shipment = Shipment::firstOrFail();
        $this->assertTrue($shipment->estimated_arrival_at->equalTo($shipment->expected_arrival_at));
    }

    public function test_status_cannot_be_forced_from_the_form(): void
    {
        $this->actingAs($this->operator)->post(route('shipments.store'), $this->validPayload([
            'status' => 'DELIVERED',
            'shipment_number' => 'HACKED-1',
            'created_by' => 999,
        ]));

        $shipment = Shipment::firstOrFail();
        $this->assertSame('CREATED', $shipment->status);
        $this->assertNotSame('HACKED-1', $shipment->shipment_number);
        $this->assertSame($this->operator->id, (int) $shipment->created_by);
    }

    public function test_arrival_must_be_after_departure(): void
    {
        $payload = $this->validPayload();
        $payload['expected_arrival_at'] = $payload['expected_departure_at'];

        $this->actingAs($this->operator)->post(route('shipments.store'), $payload)
            ->assertSessionHasErrors('expected_arrival_at');
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_origin_and_destination_must_differ(): void
    {
        $payload = $this->validPayload();
        $payload['destination_id'] = $payload['origin_id'];

        $this->actingAs($this->operator)->post(route('shipments.store'), $payload)
            ->assertSessionHasErrors('origin_id');
    }

    public function test_at_least_one_item_is_required(): void
    {
        $this->actingAs($this->operator)->post(route('shipments.store'), $this->validPayload(['items' => []]))
            ->assertSessionHasErrors('items');
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_customer_field_must_be_a_customer_account(): void
    {
        $this->actingAs($this->operator)
            ->post(route('shipments.store'), $this->validPayload(['customer_id' => $this->operator->id]))
            ->assertSessionHasErrors('customer_id');
    }

    public function test_operator_can_update_a_shipment_and_replace_items(): void
    {
        $shipment = Shipment::factory()->create();
        $shipment->items()->create(['description' => 'Old item', 'quantity' => 1]);

        $payload = $this->validPayload([
            'cargo_type' => 'Textiles',
            'items' => [['description' => 'New item A', 'quantity' => 5, 'weight_kg' => 10]],
        ]);

        $this->actingAs($this->operator)->put(route('shipments.update', $shipment), $payload)
            ->assertRedirect(route('shipments.show', $shipment));

        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'cargo_type' => 'Textiles']);
        $this->assertDatabaseMissing('shipment_items', ['description' => 'Old item']);
        $this->assertDatabaseHas('shipment_items', ['shipment_id' => $shipment->id, 'description' => 'New item A']);
    }

    public function test_list_supports_search_and_status_filter(): void
    {
        $match = Shipment::factory()->create(['shipment_number' => 'CT-20260101-AAAA']);
        $other = Shipment::factory()->create(['shipment_number' => 'CT-20260101-BBBB', 'status' => 'BOOKED']);

        $this->actingAs($this->operator)->get('/shipments?q=AAAA')
            ->assertSee($match->shipment_number)->assertDontSee($other->shipment_number);

        $this->actingAs($this->operator)->get('/shipments?status=BOOKED')
            ->assertSee($other->shipment_number)->assertDontSee($match->shipment_number);
    }

    public function test_list_is_paginated_on_the_server(): void
    {
        Shipment::factory()->count(12)->create();

        $this->actingAs($this->operator)->get('/shipments')->assertOk()->assertSee('Page 1 of 2');
        $this->actingAs($this->operator)->get('/shipments?page=2')->assertOk()->assertSee('Page 2 of 2');
    }
}
