<?php
/*
 * ============================================================
 * BUAT APA : Menguji ShipmentService langsung (tanpa HTTP).
 * TES PENTING: rollback. Kalau SATU item gagal disimpan, shipment dan item
 *              lain harus ikut batal (tidak ada data setengah jadi).
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShipments;
use Tests\TestCase;

class ShipmentServiceTest extends TestCase
{
    use BuildsShipments, RefreshDatabase;

    public function test_failed_item_rolls_back_the_whole_shipment(): void
    {
        $service = app(ShipmentService::class);
        $operator = User::factory()->operator()->create();

        $data = $this->validPayload();
        unset($data['items']);

        $items = [
            ['description' => 'Valid item', 'quantity' => 1],
            ['description' => null, 'quantity' => 1], // description wajib -> database menolak
        ];

        try {
            $service->create($data, $items, $operator);
            $this->fail('Expected the second item to fail.');
        } catch (QueryException) {
            // yang diharapkan
        }

        $this->assertDatabaseCount('shipments', 0);
        $this->assertDatabaseCount('shipment_items', 0);
    }

    public function test_terminal_shipment_cannot_be_updated_in_the_service(): void
    {
        $this->expectException(DomainException::class);

        app(ShipmentService::class)->update(Shipment::factory()->delivered()->create(), [], []);
    }

    public function test_generated_numbers_are_unique_and_well_formed(): void
    {
        $service = app(ShipmentService::class);
        $numbers = collect(range(1, 20))->map(fn () => $service->generateNumber());

        $numbers->each(fn ($n) => $this->assertMatchesRegularExpression('/^CT-\d{8}-[A-Z0-9]{4}$/', $n));
        $this->assertCount(20, $numbers->unique());
    }

    public function test_customer_scope_is_applied_inside_the_service(): void
    {
        $mine = Shipment::factory()->create();
        Shipment::factory()->count(3)->create();

        $result = app(ShipmentService::class)->paginate($mine->customer, []);

        $this->assertSame(1, $result->total());
        $this->assertSame($mine->id, $result->first()->id);
    }
}
