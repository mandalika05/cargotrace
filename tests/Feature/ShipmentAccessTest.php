<?php
/*
 * ============================================================
 * BUAT APA : Menguji HAK AKSES shipment (syarat brief: "uji akses tanpa izin
 *            melalui automated test"). Yang diuji adalah SERVER, bukan tombol.
 * ISI TES  : tamu ditolak, customer hanya melihat miliknya, customer tidak bisa
 *            membuat/mengubah, operator tidak bisa menghapus, shipment terminal
 *            tidak bisa diubah, admin bisa menghapus (soft delete).
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShipments;
use Tests\TestCase;

class ShipmentAccessTest extends TestCase
{
    use BuildsShipments, RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/shipments')->assertRedirect('/login');
    }

    public function test_customer_only_sees_own_shipments_in_the_list(): void
    {
        $mine = Shipment::factory()->create();
        $theirs = Shipment::factory()->create();

        $this->actingAs($mine->customer)
            ->get('/shipments')
            ->assertOk()
            ->assertSee($mine->shipment_number)
            ->assertDontSee($theirs->shipment_number);
    }

    public function test_customer_can_open_own_shipment(): void
    {
        $shipment = Shipment::factory()->create();

        $this->actingAs($shipment->customer)
            ->get(route('shipments.show', $shipment))
            ->assertOk()
            ->assertSee($shipment->shipment_number);
    }

    public function test_customer_cannot_open_someone_elses_shipment(): void
    {
        $shipment = Shipment::factory()->create();
        $stranger = User::factory()->customer()->create();

        $this->actingAs($stranger)->get(route('shipments.show', $shipment))->assertForbidden();
    }

    public function test_customer_cannot_create_a_shipment(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->get(route('shipments.create'))->assertForbidden();
        $this->actingAs($customer)->post(route('shipments.store'), $this->validPayload())->assertForbidden();

        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_customer_cannot_edit_even_their_own_shipment(): void
    {
        $shipment = Shipment::factory()->create();

        $this->actingAs($shipment->customer)->get(route('shipments.edit', $shipment))->assertForbidden();
        $this->actingAs($shipment->customer)->put(route('shipments.update', $shipment), $this->validPayload())->assertForbidden();
    }

    public function test_operator_cannot_delete_but_admin_can(): void
    {
        $shipment = Shipment::factory()->create();

        $this->actingAs(User::factory()->operator()->create())
            ->delete(route('shipments.destroy', $shipment))
            ->assertForbidden();
        $this->assertNotSoftDeleted('shipments', ['id' => $shipment->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('shipments.destroy', $shipment))
            ->assertRedirect(route('shipments.index'));
        $this->assertSoftDeleted('shipments', ['id' => $shipment->id]);
    }

    public function test_terminal_shipment_cannot_be_edited_by_operator(): void
    {
        $operator = User::factory()->operator()->create();
        $delivered = Shipment::factory()->delivered()->create();
        $cancelled = Shipment::factory()->cancelled()->create();

        foreach ([$delivered, $cancelled] as $shipment) {
            $this->actingAs($operator)->get(route('shipments.edit', $shipment))->assertForbidden();
            $this->actingAs($operator)->put(route('shipments.update', $shipment), $this->validPayload())->assertForbidden();
        }
    }
}
