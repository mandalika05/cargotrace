<?php
/*
 * ============================================================
 * BUAT APA : Menguji manajemen EXCEPTION (syarat brief: jenis, severity, status
 *            OPEN/INVESTIGATING/RESOLVED, waktu deteksi dan penyelesaian).
 * ISI TES  : catat exception, penanda timeline, alur status yang sah dan yang tidak,
 *            catatan penyelesaian wajib, hak akses (customer tidak boleh),
 *            shipment dibatalkan, daftar dan urutan, batas lihat customer.
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use App\Services\ExceptionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExceptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExceptionService $service;
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ExceptionService::class);
        $this->operator = User::factory()->operator()->create();
    }

    private function data(array $overrides = []): array
    {
        return array_merge([
            'type' => 'CUSTOMS_HOLD',
            'severity' => 'HIGH',
            'description' => 'Documents are being checked.',
        ], $overrides);
    }

    public function test_staff_can_report_an_exception_and_it_starts_open(): void
    {
        $shipment = Shipment::factory()->create(['status' => 'IN_TRANSIT']);

        $exception = $this->service->report($shipment, $this->data(), $this->operator);

        $this->assertSame('OPEN', $exception->status);
        $this->assertSame('HIGH', $exception->severity);
        $this->assertNotNull($exception->detected_at);
        $this->assertNull($exception->resolved_at);
        $this->assertSame($this->operator->id, $exception->reported_by);
        $this->assertDatabaseHas('tracking_events', ['shipment_id' => $shipment->id, 'event_type' => 'EXCEPTION']);
    }

    public function test_the_timeline_marker_is_visible_on_the_shipment_page(): void
    {
        $shipment = Shipment::factory()->create(['status' => 'IN_TRANSIT']);
        $this->service->report($shipment, $this->data(), $this->operator);

        $this->actingAs($this->operator)->get(route('shipments.show', $shipment))
            ->assertOk()
            ->assertSee('Exception reported')
            ->assertSee('Customs hold');
    }

    public function test_a_customer_cannot_report_or_change_exceptions(): void
    {
        $shipment = Shipment::factory()->create(['status' => 'IN_TRANSIT']);
        $exception = ShipmentException::factory()->create(['shipment_id' => $shipment->id]);

        try {
            $this->service->report($shipment, $this->data(), $shipment->customer);
            $this->fail('A customer must not report exceptions.');
        } catch (AuthorizationException) {
        }

        $this->expectException(AuthorizationException::class);
        $this->service->changeStatus($exception, 'INVESTIGATING', $shipment->customer);
    }

    public function test_invalid_type_severity_and_future_time_are_rejected(): void
    {
        $shipment = Shipment::factory()->create(['status' => 'IN_TRANSIT']);

        foreach ([
            ['type' => 'ALIENS', 'field' => 'type'],
            ['severity' => 'CRITICAL', 'field' => 'severity'],
            ['detected_at' => now()->addDay(), 'field' => 'detected_at'],
        ] as $case) {
            $field = $case['field'];
            unset($case['field']);

            try {
                $this->service->report($shipment, $this->data($case), $this->operator);
                $this->fail("Expected a validation error on {$field}.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($field, $e->errors());
            }
        }

        $this->assertDatabaseCount('shipment_exceptions', 0);
    }

    public function test_an_exception_cannot_be_reported_on_a_cancelled_shipment(): void
    {
        $shipment = Shipment::factory()->cancelled()->create();

        $this->expectException(ValidationException::class);
        $this->service->report($shipment, $this->data(), $this->operator);
    }

    public function test_a_delivered_shipment_can_still_get_an_exception(): void
    {
        $shipment = Shipment::factory()->delivered()->create();

        $exception = $this->service->report($shipment, $this->data(['type' => 'DAMAGED', 'severity' => 'MEDIUM']), $this->operator);

        $this->assertSame('DAMAGED', $exception->type);
    }

    public function test_status_flows_open_investigating_resolved(): void
    {
        $shipment = Shipment::factory()->create(['status' => 'IN_TRANSIT']);
        $exception = $this->service->report($shipment, $this->data(), $this->operator);

        $exception = $this->service->changeStatus($exception, 'INVESTIGATING', $this->operator);
        $this->assertSame('INVESTIGATING', $exception->status);

        $exception = $this->service->changeStatus($exception, 'RESOLVED', $this->operator, 'Customs cleared the cargo.');
        $this->assertSame('RESOLVED', $exception->status);
        $this->assertNotNull($exception->resolved_at);
        $this->assertSame($this->operator->id, $exception->resolved_by);
        $this->assertSame('Customs cleared the cargo.', $exception->resolution_note);

        foreach (['EXCEPTION', 'EXCEPTION_UPDATE', 'EXCEPTION_RESOLVED'] as $type) {
            $this->assertDatabaseHas('tracking_events', ['shipment_id' => $shipment->id, 'event_type' => $type]);
        }
    }

    public function test_open_can_be_resolved_directly(): void
    {
        $shipment = Shipment::factory()->create(['status' => 'IN_TRANSIT']);
        $exception = $this->service->report($shipment, $this->data(), $this->operator);

        $this->assertSame('RESOLVED', $this->service->changeStatus($exception, 'RESOLVED', $this->operator, 'Fixed on site.')->status);
    }

    public function test_resolving_needs_a_note(): void
    {
        $shipment = Shipment::factory()->create(['status' => 'IN_TRANSIT']);
        $exception = $this->service->report($shipment, $this->data(), $this->operator);

        try {
            $this->service->changeStatus($exception, 'RESOLVED', $this->operator, ' ');
            $this->fail('A resolution note is required.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('resolution_note', $e->errors());
        }

        $this->assertSame('OPEN', $exception->fresh()->status);
    }

    public function test_a_resolved_exception_cannot_go_back(): void
    {
        $exception = ShipmentException::factory()->resolved()->create();

        foreach (['OPEN', 'INVESTIGATING', 'RESOLVED'] as $to) {
            try {
                $this->service->changeStatus($exception, $to, $this->operator, 'Trying again');
                $this->fail("RESOLVED -> {$to} must be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('status', $e->errors());
            }
        }

        $this->assertSame('RESOLVED', $exception->fresh()->status);
    }

    public function test_investigating_cannot_go_back_to_open(): void
    {
        $exception = ShipmentException::factory()->investigating()->create();

        $this->expectException(ValidationException::class);
        $this->service->changeStatus($exception, 'OPEN', $this->operator);
    }

    public function test_the_list_puts_urgent_unresolved_exceptions_first(): void
    {
        $resolvedHigh = ShipmentException::factory()->resolved()->high()->create();
        $openLow = ShipmentException::factory()->create(['severity' => 'LOW']);
        $openHigh = ShipmentException::factory()->high()->create();
        $investigating = ShipmentException::factory()->investigating()->high()->create();

        $ids = $this->service->paginate($this->operator)->pluck('id')->all();

        $this->assertSame([$openHigh->id, $openLow->id, $investigating->id, $resolvedHigh->id], $ids);
    }

    public function test_filters_narrow_the_list(): void
    {
        ShipmentException::factory()->create(['type' => 'DAMAGED', 'severity' => 'LOW']);
        $target = ShipmentException::factory()->high()->create(['type' => 'CUSTOMS_HOLD']);

        $this->assertSame([$target->id], $this->service->paginate($this->operator, ['type' => 'CUSTOMS_HOLD', 'severity' => 'HIGH'])->pluck('id')->all());
        $this->assertSame([$target->id], $this->service->paginate($this->operator, ['q' => $target->shipment->shipment_number])->pluck('id')->all());
        $this->assertCount(2, $this->service->paginate($this->operator, ['status' => 'bogus', 'type' => 'bogus']));
    }

    public function test_a_customer_only_sees_exceptions_on_their_own_shipments(): void
    {
        $mine = ShipmentException::factory()->create();
        ShipmentException::factory()->count(3)->create();

        $customer = $mine->shipment->customer;

        $this->assertSame([$mine->id], $this->service->paginate($customer)->pluck('id')->all());
        $this->assertSame(1, $this->service->summary($customer)['unresolved']);
        $this->assertSame(4, $this->service->summary($this->operator)['unresolved']);
    }

    public function test_the_summary_counts_by_status(): void
    {
        ShipmentException::factory()->count(2)->create();
        ShipmentException::factory()->investigating()->create();
        ShipmentException::factory()->resolved()->count(3)->create();

        $this->assertSame(
            ['open' => 2, 'investigating' => 1, 'resolved' => 3, 'unresolved' => 3],
            $this->service->summary($this->operator)
        );
    }

    public function test_deleted_shipments_do_not_leak_into_the_list(): void
    {
        $exception = ShipmentException::factory()->create();
        $exception->shipment->delete(); // soft delete

        $this->assertCount(0, $this->service->paginate($this->operator));
    }
}
