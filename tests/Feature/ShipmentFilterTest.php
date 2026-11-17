<?php
/*
 * ============================================================
 * BUAT APA : Menguji pencarian dan filter lanjutan daftar shipment (P13.2): origin,
 *            destination, rentang tanggal, status delay, validasi, pembatasan customer,
 *            pagination yang membawa filter, API, dan export CSV.
 * ISI TES  : aturan delay memakai EtaService yang sama dengan lencana (batas 6 jam tepat
 *            masih LOW, 6 jam 1 menit sudah MEDIUM); shipment CANCELLED tidak pernah cocok.
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShipmentFilterTest extends TestCase
{
    use RefreshDatabase;

    /** Nomor shipment hasil filter, diurutkan supaya mudah dibandingkan. */
    private function numbers(User $user, array $filters): array
    {
        return app(ShipmentService::class)->paginate($user, $filters, 50)
            ->getCollection()->pluck('shipment_number')->sort()->values()->all();
    }

    /** @param array<string, mixed> $attrs */
    private function ship(string $number, array $attrs = []): Shipment
    {
        return Shipment::factory()->create(array_merge(['shipment_number' => $number], $attrs));
    }

    // ---------- Origin dan destination ----------

    public function test_filters_by_origin_and_destination(): void
    {
        $staff = User::factory()->operator()->create();
        $l1 = Location::factory()->create();
        $l2 = Location::factory()->create();

        $this->ship('CT-F-S1', ['origin_id' => $l1->id, 'destination_id' => $l2->id]);
        $this->ship('CT-F-S2', ['origin_id' => $l2->id, 'destination_id' => $l1->id]);
        $this->ship('CT-F-S3', ['origin_id' => $l1->id, 'destination_id' => $l1->id]);

        $this->assertSame(['CT-F-S1', 'CT-F-S3'], $this->numbers($staff, ['origin_id' => $l1->id]));
        $this->assertSame(['CT-F-S2', 'CT-F-S3'], $this->numbers($staff, ['destination_id' => $l1->id]));
        $this->assertSame(['CT-F-S1'], $this->numbers($staff, ['origin_id' => $l1->id, 'destination_id' => $l2->id]));
    }

    // ---------- Rentang tanggal ----------

    public function test_date_range_includes_both_ends_of_the_day(): void
    {
        $staff = User::factory()->operator()->create();

        $this->ship('CT-D-1', ['estimated_arrival_at' => '2026-12-01 10:00:00']);
        $this->ship('CT-D-2', ['estimated_arrival_at' => '2026-12-05 23:30:00']);
        $this->ship('CT-D-3', ['estimated_arrival_at' => '2026-12-10 08:00:00']);

        // 5 Desember pukul 23:30 tetap masuk karena hari terakhir dihitung penuh
        $this->assertSame(['CT-D-2'], $this->numbers($staff, ['date_from' => '2026-12-02', 'date_to' => '2026-12-05']));
        $this->assertSame(['CT-D-1', 'CT-D-2'], $this->numbers($staff, ['date_to' => '2026-12-05']));
        $this->assertSame(['CT-D-2', 'CT-D-3'], $this->numbers($staff, ['date_from' => '2026-12-05']));
        $this->assertSame(['CT-D-1'], $this->numbers($staff, ['date_from' => '2026-12-01', 'date_to' => '2026-12-01']));
    }

    public function test_the_date_field_can_be_departure_or_created(): void
    {
        $staff = User::factory()->operator()->create();

        $this->ship('CT-DF-1', ['expected_departure_at' => '2026-12-03 08:00:00', 'estimated_arrival_at' => '2026-12-20 08:00:00', 'created_at' => '2026-11-01 09:00:00']);
        $this->ship('CT-DF-2', ['expected_departure_at' => '2026-12-15 08:00:00', 'estimated_arrival_at' => '2026-12-04 08:00:00', 'created_at' => '2026-11-20 09:00:00']);

        $range = ['date_from' => '2026-12-01', 'date_to' => '2026-12-05'];

        $this->assertSame(['CT-DF-2'], $this->numbers($staff, $range));                                  // bawaan: ETA
        $this->assertSame(['CT-DF-1'], $this->numbers($staff, $range + ['date_field' => 'departure']));
        $this->assertSame(['CT-DF-2'], $this->numbers($staff, ['date_from' => '2026-11-10', 'date_to' => '2026-11-30', 'date_field' => 'created']));
    }

    public function test_a_garbage_date_is_ignored_by_the_service(): void
    {
        $staff = User::factory()->operator()->create();
        $this->ship('CT-G-1');

        $this->assertSame(['CT-G-1'], $this->numbers($staff, ['date_from' => 'not-a-date', 'date_to' => '31/12/2026']));
    }

    // ---------- Status delay ----------

    // 8 shipment dengan keterlambatan yang sudah diketahui (lihat nama nomornya)
    private function delayScenario(): void
    {
        $future = now()->addDays(3)->startOfHour();
        $past = now()->subDays(3)->startOfHour();
        $running = fn (string $n, $eta) => $this->ship($n, ['status' => 'IN_TRANSIT', 'expected_arrival_at' => $future, 'estimated_arrival_at' => $eta]);

        $running('CT-L-ONTIME', $future);
        $running('CT-L-LOW6H', $future->copy()->addHours(6));                 // tepat 6 jam = LOW
        $running('CT-L-MED6H1', $future->copy()->addHours(6)->addMinute());   // 6 jam 1 menit = MEDIUM
        $running('CT-L-HIGH30', $future->copy()->addHours(30));
        $this->ship('CT-L-OVERDUE', ['status' => 'IN_TRANSIT', 'expected_arrival_at' => $past, 'estimated_arrival_at' => $past]); // jadwal lewat 3 hari = HIGH
        $this->ship('CT-L-DELIVLATE', ['status' => 'DELIVERED', 'expected_arrival_at' => $past, 'estimated_arrival_at' => $past, 'delivered_at' => $past->copy()->addHours(10)]); // MEDIUM
        $this->ship('CT-L-DELIVOK', ['status' => 'DELIVERED', 'expected_arrival_at' => $past, 'estimated_arrival_at' => $past, 'delivered_at' => $past]);
        $this->ship('CT-L-CANCEL', ['status' => 'CANCELLED', 'cancelled_at' => now(), 'expected_arrival_at' => $future, 'estimated_arrival_at' => $future->copy()->addHours(30)]);

    }

    public function test_delay_filter_follows_the_eta_rules(): void
    {
        $staff = User::factory()->operator()->create();
        $this->delayScenario();

        $this->assertSame(['CT-L-DELIVLATE', 'CT-L-HIGH30', 'CT-L-LOW6H', 'CT-L-MED6H1', 'CT-L-OVERDUE'], $this->numbers($staff, ['delay' => 'delayed']));
        $this->assertSame(['CT-L-LOW6H'], $this->numbers($staff, ['delay' => 'low']));
        $this->assertSame(['CT-L-DELIVLATE', 'CT-L-MED6H1'], $this->numbers($staff, ['delay' => 'medium']));
        $this->assertSame(['CT-L-HIGH30', 'CT-L-OVERDUE'], $this->numbers($staff, ['delay' => 'high']));
        $this->assertSame(['CT-L-DELIVOK', 'CT-L-ONTIME'], $this->numbers($staff, ['delay' => 'on_time']));
    }

    public function test_delay_filter_combines_with_other_filters(): void
    {
        $staff = User::factory()->operator()->create();
        $this->delayScenario();

        $this->assertSame(['CT-L-HIGH30'], $this->numbers($staff, ['delay' => 'high', 'status' => 'IN_TRANSIT', 'q' => 'HIGH30']));
        $this->assertSame([], $this->numbers($staff, ['delay' => 'high', 'status' => 'DELIVERED']));
    }

    // ---------- Customer ----------

    public function test_a_customer_filter_never_shows_other_peoples_shipments_or_locations(): void
    {
        $me = User::factory()->customer()->create();
        $mine = Location::factory()->create(['name' => 'Mine Harbour']);
        $secret = Location::factory()->create(['name' => 'Secret Dock X']);

        $this->ship('CT-C-MINE', ['customer_id' => $me->id, 'origin_id' => $mine->id, 'destination_id' => $mine->id]);
        $this->ship('CT-C-OTHER', ['origin_id' => $secret->id, 'destination_id' => $secret->id]);

        // Filter yang cocok dengan milik orang lain tetap tidak membocorkan apa pun
        $this->assertSame([], $this->numbers($me, ['origin_id' => $secret->id]));
        $this->assertSame(['CT-C-MINE'], $this->numbers($me, []));

        // Pilihan lokasi di dropdown hanya lokasi dari shipment miliknya
        $this->actingAs($me)->get('/shipments')
            ->assertOk()->assertSee('Mine Harbour')->assertDontSee('Secret Dock X');
    }

    // ---------- Halaman web ----------

    public function test_the_page_shows_the_advanced_filters_and_applies_them(): void
    {
        $staff = User::factory()->operator()->create();
        $l1 = Location::factory()->create();
        $this->ship('CT-W-HIT', ['origin_id' => $l1->id]);
        $this->ship('CT-W-MISS');

        $this->actingAs($staff)->get('/shipments?origin_id='.$l1->id)
            ->assertOk()
            ->assertSee('More filters')
            ->assertSee('Delay status')
            ->assertSee('CT-W-HIT')
            ->assertDontSee('CT-W-MISS');
    }

    public function test_pagination_links_keep_the_filters(): void
    {
        $staff = User::factory()->operator()->create();
        $future = now()->addDays(3)->startOfHour();
        for ($i = 1; $i <= 12; $i++) {
            $this->ship(sprintf('CT-P-%02d', $i), ['status' => 'IN_TRANSIT', 'expected_arrival_at' => $future, 'estimated_arrival_at' => $future->copy()->addHours(30)]);
        }

        $this->actingAs($staff)->get('/shipments?delay=high')
            ->assertOk()->assertSee('delay=high', false)->assertSee('page=2', false);
    }

    public function test_invalid_filters_are_rejected_on_the_web(): void
    {
        $staff = User::factory()->operator()->create();

        $this->actingAs($staff)->get('/shipments?status=NOPE')->assertSessionHasErrors('status');
        $this->actingAs($staff)->get('/shipments?delay=sometimes')->assertSessionHasErrors('delay');
        $this->actingAs($staff)->get('/shipments?origin_id=999999')->assertSessionHasErrors('origin_id');
        $this->actingAs($staff)->get('/shipments?date_from=2026-12-10&date_to=2026-12-01')->assertSessionHasErrors('date_to');
        $this->actingAs($staff)->get('/shipments?date_from=10-12-2026')->assertSessionHasErrors('date_from');
        $this->actingAs($staff)->get('/shipments?sort=password')->assertSessionHasErrors('sort');
    }

    public function test_empty_filter_values_from_the_form_are_accepted(): void
    {
        $staff = User::factory()->operator()->create();
        $this->ship('CT-E-1');

        // Form yang dikirim tanpa diisi menghasilkan parameter kosong: tidak boleh error
        $this->actingAs($staff)
            ->get('/shipments?q=&status=&carrier_id=&sort=newest&origin_id=&destination_id=&delay=&date_field=eta&date_from=&date_to=')
            ->assertOk()->assertSessionHasNoErrors()->assertSee('CT-E-1');
    }

    // ---------- REST API dan export ----------

    public function test_the_api_applies_advanced_filters_and_returns_422_for_bad_ones(): void
    {
        Sanctum::actingAs(User::factory()->operator()->create());
        $future = now()->addDays(3)->startOfHour();
        $this->ship('CT-A-HIGH', ['status' => 'IN_TRANSIT', 'expected_arrival_at' => $future, 'estimated_arrival_at' => $future->copy()->addHours(30)]);
        $this->ship('CT-A-OK', ['status' => 'IN_TRANSIT', 'expected_arrival_at' => $future, 'estimated_arrival_at' => $future]);

        $this->getJson('/api/v1/shipments?delay=high')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.shipment_number', 'CT-A-HIGH');

        $this->getJson('/api/v1/shipments?delay=wrong')
            ->assertStatus(422)->assertJsonValidationErrors(['delay']);

        $this->getJson('/api/v1/shipments?date_from=2026-12-10&date_to=2026-12-01')
            ->assertStatus(422)->assertJsonValidationErrors(['date_to']);
    }

    public function test_the_csv_export_follows_the_filters(): void
    {
        $staff = User::factory()->operator()->create();
        $l1 = Location::factory()->create();
        $this->ship('CT-X-IN', ['origin_id' => $l1->id]);
        $this->ship('CT-X-OUT');

        $csv = $this->actingAs($staff)->get('/exports/shipments.csv?origin_id='.$l1->id)->assertOk()->streamedContent();

        $this->assertStringContainsString('CT-X-IN', $csv);
        $this->assertStringNotContainsString('CT-X-OUT', $csv);
    }
}
