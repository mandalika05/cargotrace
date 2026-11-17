<?php
/*
 * ============================================================
 * BUAT APA : Menguji KPI dan data grafik dashboard (P13.1): angkanya harus cocok dengan
 *            data di database, terbatas per peran, dan aman saat data kosong.
 * DATA UJI : 5 shipment dengan angka yang mudah dihitung (lihat seedScenario()).
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardInsightsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 5 shipment:
     *  A  DELIVERED tepat waktu
     *  B  DELIVERED terlambat 10 jam (600 menit)
     *  C  IN_TRANSIT, ETA mundur 30 jam (1800 menit), punya exception RESOLVED (120 menit)
     *  D  IN_TRANSIT tepat waktu, punya exception OPEN
     *  E  CANCELLED
     * Aktif (tidak dibatalkan) = 4; terlambat = B dan C.
     *
     * @return array<string, Shipment>
     */
    private function seedScenario(?User $customer = null): array
    {
        $customerId = $customer?->id;
        $own = $customerId ? ['customer_id' => $customerId] : [];

        $past = now()->subDays(3)->startOfHour();
        $future = now()->addDays(2)->startOfHour();

        $a = Shipment::factory()->create($own + ['status' => 'DELIVERED', 'expected_arrival_at' => $past, 'estimated_arrival_at' => $past, 'delivered_at' => $past]);
        $b = Shipment::factory()->create($own + ['status' => 'DELIVERED', 'expected_arrival_at' => $past, 'estimated_arrival_at' => $past, 'delivered_at' => $past->copy()->addHours(10)]);
        $c = Shipment::factory()->create($own + ['status' => 'IN_TRANSIT', 'expected_arrival_at' => $future, 'estimated_arrival_at' => $future->copy()->addHours(30)]);
        $d = Shipment::factory()->create($own + ['status' => 'IN_TRANSIT', 'expected_arrival_at' => $future, 'estimated_arrival_at' => $future]);
        $e = Shipment::factory()->cancelled()->create($own);

        $t = now()->startOfMinute();
        ShipmentException::factory()->create([
            'shipment_id' => $c->id, 'type' => 'WEATHER_DELAY', 'status' => 'RESOLVED',
            'detected_at' => $t->copy()->subHours(3), 'resolved_at' => $t->copy()->subHour(), 'resolution_note' => 'Done.',
        ]);
        ShipmentException::factory()->create(['shipment_id' => $d->id, 'type' => 'DAMAGED']);

        return compact('a', 'b', 'c', 'd', 'e');
    }

    public function test_kpis_match_the_data(): void
    {
        $this->seedScenario();
        $staff = User::factory()->operator()->create();

        $k = app(DashboardService::class)->build($staff)['kpis'];

        $this->assertSame(4, $k['active']);
        $this->assertSame(2, $k['delivered']);
        $this->assertSame(50.0, $k['on_time_rate']);      // 1 dari 2 tiba tepat waktu
        $this->assertSame(50.0, $k['delay_rate']);        // B dan C dari 4 aktif
        $this->assertSame(50.0, $k['exception_rate']);    // C dan D dari 4 aktif
        $this->assertSame(1200, $k['avg_delay_minutes']); // (600 + 1800) / 2
        $this->assertSame(120, $k['avg_resolution_minutes']);
    }

    public function test_chart_data_match_the_data(): void
    {
        $this->seedScenario();
        $staff = User::factory()->operator()->create();

        $charts = app(DashboardService::class)->build($staff)['charts'];

        // Batang status (urutan tetap seperti mockup dosen), 'total' = semua shipment
        $status = $charts['status'];
        $this->assertSame(['Awaiting pickup', 'In transit', 'Delivered', 'Delayed', 'Open exceptions'], $status['labels']);
        $this->assertSame([0, 2, 2, 1, 1], $status['values']);   // C sedang terlambat; hanya exception D yang belum selesai
        $this->assertCount(5, $status['colors']);
        $this->assertSame(5, $status['total']);                  // termasuk yang dibatalkan

        // Donut: B dan C terlambat, masing-masing carrier berbeda -> 50% dan 50%
        $donut = $charts['delay_by_carrier'];
        $this->assertSame(2, $donut['total']);
        $this->assertSame([1, 1], $donut['values']);
        $this->assertSame([50.0, 50.0], $donut['percents']);
        $this->assertSame(50.0, $donut['center']);               // delay rate keseluruhan
        $this->assertCount(2, $donut['colors']);

        $byType = array_combine($charts['exception_type']['labels'], $charts['exception_type']['values']);
        $this->assertCount(2, $byType);

        // Tren: selalu 6 bulan; bulan ini berisi 5 shipment yang baru dibuat
        $this->assertCount(6, $charts['trend']['labels']);
        $this->assertSame(5, $charts['trend']['values'][5]);
    }

    public function test_the_donut_groups_carriers_beyond_five_as_others(): void
    {
        $staff = User::factory()->operator()->create();
        $future = now()->addDays(2)->startOfHour();

        // 7 shipment terlambat, tiap kali carrier berbeda
        for ($i = 0; $i < 7; $i++) {
            Shipment::factory()->create(['status' => 'IN_TRANSIT', 'expected_arrival_at' => $future, 'estimated_arrival_at' => $future->copy()->addHours(30)]);
        }

        $donut = app(DashboardService::class)->build($staff)['charts']['delay_by_carrier'];

        $this->assertCount(6, $donut['labels']);          // 5 carrier + Others
        $this->assertSame('Others', $donut['labels'][5]);
        $this->assertSame(2, $donut['values'][5]);
        $this->assertSame(7, array_sum($donut['values']));
    }

    public function test_a_customer_only_counts_their_own_data(): void
    {
        $me = User::factory()->customer()->create();
        $this->seedScenario($me);
        $this->seedScenario(); // milik customer lain, tidak boleh ikut

        $k = app(DashboardService::class)->build($me)['kpis'];
        $charts = app(DashboardService::class)->build($me)['charts'];

        $this->assertSame(4, $k['active']);
        $this->assertSame(5, $charts['status']['total']);   // hanya 5 milik customer ini
    }

    public function test_empty_data_gives_null_rates_without_errors(): void
    {
        $staff = User::factory()->operator()->create();

        $k = app(DashboardService::class)->build($staff)['kpis'];

        $this->assertNull($k['on_time_rate']);
        $this->assertNull($k['delay_rate']);
        $this->assertNull($k['exception_rate']);
        $this->assertNull($k['avg_delay_minutes']);
        $this->assertNull($k['avg_resolution_minutes']);

        $this->actingAs($staff)->get(route('dashboard'))
            ->assertOk()->assertSee('No data to chart yet.');
    }

    public function test_dashboard_page_shows_kpis_and_chart_canvases(): void
    {
        $this->seedScenario();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('On-time delivery rate')
            ->assertSee('50.0%')
            ->assertSee('20h 00m')
            ->assertSee('data-chart', false)
            ->assertSee('Shipments by Status')
            ->assertSee('Delay Rate by Carrier')
            ->assertSee('Shipment Map')
            ->assertSee('Recent Shipments')
            ->assertSee('Total shipments');
    }

    public function test_the_customer_dashboard_also_shows_insights(): void
    {
        $me = User::factory()->customer()->create();
        $this->seedScenario($me);

        $this->actingAs($me)->get(route('dashboard'))
            ->assertOk()->assertSee('Delay rate')->assertSee('data-chart', false);
    }

    // ---------- Peta dashboard ----------

    public function test_the_dashboard_map_lists_only_running_shipments_with_positions(): void
    {
        $this->seedScenario();                       // C dan D berjalan; A, B selesai; E dibatalkan
        $staff = User::factory()->operator()->create();

        $json = $this->actingAs($staff)->getJson(route('dashboard.map'))->assertOk()->json('data');

        $this->assertTrue($json['available']);
        $this->assertCount(2, $json['points']);
        $this->assertContains(true, array_column($json['points'], 'delayed'));   // C terlambat
        $this->assertSame(['origin'], array_unique(array_column($json['points'], 'source')));
    }

    public function test_the_map_uses_the_latest_tracking_point_when_there_is_one(): void
    {
        $staff = User::factory()->operator()->create();
        $s = Shipment::factory()->create(['status' => 'IN_TRANSIT']);
        $s->trackingEvents()->forceCreate(['shipment_id' => $s->id, 'event_type' => 'LOCATION_UPDATE', 'latitude' => 5.5, 'longitude' => 100.1, 'occurred_at' => now()->subHours(8)]);
        $s->trackingEvents()->forceCreate(['shipment_id' => $s->id, 'event_type' => 'LOCATION_UPDATE', 'latitude' => 12.5, 'longitude' => 43.3, 'occurred_at' => now()->subHours(2)]);

        $point = $this->actingAs($staff)->getJson(route('dashboard.map'))->json('data.points.0');

        $this->assertSame('current', $point['source']);
        $this->assertSame(12.5, $point['latitude']);   // titik terbaru
    }

    public function test_the_map_is_empty_without_running_shipments_and_scoped_for_customers(): void
    {
        $me = User::factory()->customer()->create();
        $this->seedScenario();                         // milik orang lain

        $this->actingAs($me)->getJson(route('dashboard.map'))
            ->assertOk()->assertJsonPath('data.available', false)->assertJsonCount(0, 'data.points');

        $this->seedScenario($me);
        $this->actingAs($me)->getJson(route('dashboard.map'))->assertJsonCount(2, 'data.points');
    }

    public function test_the_map_requires_login(): void
    {
        $this->getJson(route('dashboard.map'))->assertUnauthorized();
    }
}
