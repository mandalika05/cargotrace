<?php
/*
 * ============================================================
 * BUAT APA : Menguji halaman web exception dan ETA (hak akses di SERVER, bukan hanya
 *            tombol yang disembunyikan) - sesuai brief: "uji akses tanpa izin lewat tes".
 * ISI TES  : staf mencatat / menginvestigasi / menyelesaikan, customer ditolak (403),
 *            customer hanya melihat miliknya, exception RESOLVED tidak bisa diubah,
 *            catatan wajib, daftar dan filter, pembaruan ETA, lencana delay.
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExceptionWebTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = User::factory()->operator()->create();
        $this->shipment = Shipment::factory()->create(['status' => 'IN_TRANSIT']);
    }

    private function report(array $overrides = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->operator)->post(
            route('shipments.exceptions.store', $this->shipment),
            array_merge(['type' => 'PORT_CONGESTION', 'severity' => 'HIGH', 'description' => 'Queue of 12 vessels.'], $overrides)
        );
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('exceptions.index'))->assertRedirect(route('login'));
        $this->post(route('shipments.exceptions.store', $this->shipment), [])->assertRedirect(route('login'));
    }

    public function test_staff_can_report_an_exception_from_the_shipment_page(): void
    {
        $this->report()->assertRedirect(route('shipments.show', $this->shipment).'#exceptions');

        $this->assertDatabaseHas('shipment_exceptions', [
            'shipment_id' => $this->shipment->id, 'type' => 'PORT_CONGESTION', 'severity' => 'HIGH',
            'status' => 'OPEN', 'reported_by' => $this->operator->id,
        ]);
    }

    public function test_report_validation_rejects_bad_input(): void
    {
        $this->report(['type' => 'ALIENS'])->assertSessionHasErrors('type');
        $this->report(['severity' => 'CRITICAL'])->assertSessionHasErrors('severity');
        $this->report(['detected_at' => now()->addDay()->format('Y-m-d\TH:i')])->assertSessionHasErrors('detected_at');
        $this->report(['type' => null])->assertSessionHasErrors('type');

        $this->assertDatabaseCount('shipment_exceptions', 0);
    }

    public function test_reporting_on_a_cancelled_shipment_is_refused(): void
    {
        $this->shipment = Shipment::factory()->cancelled()->create();

        $this->report()->assertSessionHasErrors('type');
        $this->assertDatabaseCount('shipment_exceptions', 0);
    }

    public function test_a_customer_cannot_report_investigate_or_resolve(): void
    {
        $customer = $this->shipment->customer;
        $exception = ShipmentException::factory()->create(['shipment_id' => $this->shipment->id]);

        $this->report([], $customer)->assertForbidden();
        $this->actingAs($customer)->patch(route('exceptions.update', $exception), ['status' => 'INVESTIGATING'])->assertForbidden();
        $this->actingAs($customer)->patch(route('exceptions.update', $exception), ['status' => 'RESOLVED', 'note' => 'I fixed it myself'])->assertForbidden();

        $this->assertSame('OPEN', $exception->fresh()->status);
        $this->assertDatabaseCount('shipment_exceptions', 1);
    }

    public function test_staff_moves_an_exception_through_its_lifecycle(): void
    {
        $exception = ShipmentException::factory()->create(['shipment_id' => $this->shipment->id]);

        $this->actingAs($this->operator)->patch(route('exceptions.update', $exception), ['status' => 'INVESTIGATING'])
            ->assertRedirect(route('shipments.show', $this->shipment).'#exceptions');
        $this->assertSame('INVESTIGATING', $exception->fresh()->status);

        $this->actingAs($this->operator)->patch(route('exceptions.update', $exception), ['status' => 'RESOLVED', 'note' => 'Berth became available.']);
        $exception->refresh();
        $this->assertSame('RESOLVED', $exception->status);
        $this->assertSame('Berth became available.', $exception->resolution_note);
        $this->assertNotNull($exception->resolved_at);
    }

    public function test_resolving_without_a_note_is_refused(): void
    {
        $exception = ShipmentException::factory()->create(['shipment_id' => $this->shipment->id]);

        $this->actingAs($this->operator)->patch(route('exceptions.update', $exception), ['status' => 'RESOLVED'])
            ->assertSessionHasErrors('resolution_note');

        $this->assertSame('OPEN', $exception->fresh()->status);
    }

    public function test_a_resolved_exception_cannot_be_changed_again(): void
    {
        $exception = ShipmentException::factory()->resolved()->create(['shipment_id' => $this->shipment->id]);

        $this->actingAs($this->operator)->patch(route('exceptions.update', $exception), ['status' => 'INVESTIGATING'])->assertForbidden();
        $this->assertSame('RESOLVED', $exception->fresh()->status);
    }

    public function test_status_input_is_limited_to_the_allowed_values(): void
    {
        $exception = ShipmentException::factory()->create(['shipment_id' => $this->shipment->id]);

        $this->actingAs($this->operator)->patch(route('exceptions.update', $exception), ['status' => 'OPEN'])->assertSessionHasErrors('status');
        $this->actingAs($this->operator)->patch(route('exceptions.update', $exception), ['status' => 'DELETED'])->assertSessionHasErrors('status');
    }

    public function test_the_shipment_page_shows_exceptions_to_staff_with_actions(): void
    {
        ShipmentException::factory()->high()->create(['shipment_id' => $this->shipment->id, 'type' => 'CUSTOMS_HOLD']);

        $this->actingAs($this->operator)->get(route('shipments.show', $this->shipment))
            ->assertOk()
            ->assertSee('Customs hold')
            ->assertSee('Start investigation')
            ->assertSee('Report an exception')
            ->assertSee('Update ETA');
    }

    public function test_the_owner_sees_exceptions_but_gets_no_action_forms(): void
    {
        ShipmentException::factory()->create(['shipment_id' => $this->shipment->id, 'type' => 'WEATHER_DELAY']);

        $this->actingAs($this->shipment->customer)->get(route('shipments.show', $this->shipment))
            ->assertOk()
            ->assertSee('Weather delay')
            ->assertDontSee('Start investigation')
            ->assertDontSee('Report an exception')
            ->assertDontSee('Update ETA');
    }

    public function test_the_list_page_is_scoped_per_role(): void
    {
        // Pakai NOMOR SHIPMENT (unik) untuk memeriksa baris, bukan nama jenis: nama jenis
        // selalu muncul di dropdown filter sehingga assertDontSee akan salah.
        $mine = ShipmentException::factory()->create(['shipment_id' => $this->shipment->id, 'type' => 'DAMAGED']);
        $other = ShipmentException::factory()->create(['type' => 'MISROUTED']);

        $this->actingAs($this->shipment->customer)->get(route('exceptions.index'))
            ->assertOk()
            ->assertSee($mine->shipment->shipment_number)
            ->assertDontSee($other->shipment->shipment_number);

        $this->actingAs($this->operator)->get(route('exceptions.index'))
            ->assertOk()
            ->assertSee($mine->shipment->shipment_number)
            ->assertSee($other->shipment->shipment_number);
    }

    public function test_the_list_page_filters_by_severity(): void
    {
        $high = ShipmentException::factory()->high()->create(['type' => 'CUSTOMS_HOLD']);
        $low = ShipmentException::factory()->create(['type' => 'DAMAGED', 'severity' => 'LOW']);

        // Dicek lewat data daftar (bukan teks seluruh halaman): dropdown lonceng (P13.3) juga
        // memuat nomor shipment dari notifikasi, jadi assertDontSee pada halaman penuh akan salah.
        $response = $this->actingAs($this->operator)->get(route('exceptions.index', ['severity' => 'HIGH']))
            ->assertOk()
            ->assertSee($high->shipment->shipment_number);

        $ids = $response->viewData('exceptions')->pluck('id')->all();
        $this->assertContains($high->id, $ids);
        $this->assertNotContains($low->id, $ids);
    }

    // ---------- ETA ----------

    public function test_staff_can_update_the_eta_and_the_page_shows_the_delay(): void
    {
        $shipment = Shipment::factory()->create([
            'status' => 'IN_TRANSIT',
            'expected_departure_at' => now()->subDays(10),
            'expected_arrival_at' => now()->addDays(5)->startOfMinute(),
            'estimated_arrival_at' => now()->addDays(5)->startOfMinute(),
        ]);
        $newEta = $shipment->expected_arrival_at->copy()->addHours(30);

        $this->actingAs($this->operator)->patch(route('shipments.eta.update', $shipment), [
            'estimated_arrival_at' => $newEta->format('Y-m-d\TH:i'),
            'reason' => 'Storm at the Suez Canal',
        ])->assertRedirect(route('shipments.show', $shipment).'#delay');

        $this->assertTrue($shipment->fresh()->estimated_arrival_at->equalTo($newEta));

        $this->actingAs($this->operator)->get(route('shipments.show', $shipment))
            ->assertOk()
            ->assertSee('Delay & ETA')
            ->assertSee('Delayed - High')
            ->assertSee('Storm at the Suez Canal')   // dari timeline (event ETA_UPDATED)
            ->assertSee('ETA updated');
    }

    public function test_eta_update_needs_a_reason_and_is_forbidden_for_customers(): void
    {
        $this->actingAs($this->operator)->patch(route('shipments.eta.update', $this->shipment), [
            'estimated_arrival_at' => now()->addDays(40)->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('reason');

        $this->actingAs($this->shipment->customer)->patch(route('shipments.eta.update', $this->shipment), [
            'estimated_arrival_at' => now()->addDays(40)->format('Y-m-d\TH:i'),
            'reason' => 'Just because I can',
        ])->assertForbidden();
    }

    public function test_eta_cannot_be_changed_on_a_delivered_shipment(): void
    {
        $delivered = Shipment::factory()->delivered()->create();

        $this->actingAs($this->operator)->patch(route('shipments.eta.update', $delivered), [
            'estimated_arrival_at' => now()->addDays(40)->format('Y-m-d\TH:i'),
            'reason' => 'Trying anyway',
        ])->assertForbidden();
    }

    public function test_the_shipment_list_flags_delayed_shipments(): void
    {
        Shipment::factory()->create([
            'status' => 'IN_TRANSIT',
            'expected_departure_at' => now()->subDays(20),
            'expected_arrival_at' => now()->subHours(30),
            'estimated_arrival_at' => now()->subHours(30),
        ]);

        $this->actingAs($this->operator)->get(route('shipments.index'))
            ->assertOk()
            ->assertSee('Delayed - High');
    }
}
