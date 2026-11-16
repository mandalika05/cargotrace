<?php
/*
 * ============================================================
 * BUAT APA : Menguji antrean "Needs attention", halaman Settings (ambang delay,
 *            khusus Admin), dan dua angka baru di dashboard (delayed, open exceptions).
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use App\Services\AttentionService;
use App\Services\EtaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttentionAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** Shipment IN_TRANSIT yang ETA-nya melewati jadwal sebanyak $lateHours jam. */
    private function late(int $lateHours, array $extra = []): Shipment
    {
        $expected = now()->addDays(4)->startOfMinute();

        return Shipment::factory()->create(array_merge([
            'status' => 'IN_TRANSIT',
            'expected_departure_at' => now()->subDays(15),
            'expected_arrival_at' => $expected,
            'estimated_arrival_at' => $expected->copy()->addHours($lateHours),
        ], $extra));
    }

    private function onTime(): Shipment
    {
        return $this->late(0);
    }

    // ---------- Needs attention ----------

    public function test_only_staff_can_open_the_queue(): void
    {
        $this->get(route('attention.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->customer()->create())->get(route('attention.index'))->assertForbidden();
        $this->actingAs(User::factory()->operator()->create())->get(route('attention.index'))->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get(route('attention.index'))->assertOk();
    }

    public function test_the_queue_contains_only_delayed_or_troubled_running_shipments(): void
    {
        $delayed = $this->late(3);
        $troubled = $this->onTime();
        ShipmentException::factory()->create(['shipment_id' => $troubled->id]);
        $fine = $this->onTime();
        $resolvedOnly = $this->onTime();
        ShipmentException::factory()->resolved()->create(['shipment_id' => $resolvedOnly->id]);
        $deliveredLate = Shipment::factory()->delivered()->create();
        ShipmentException::factory()->create(['shipment_id' => $deliveredLate->id]);

        $ids = app(AttentionService::class)->queue()->pluck('shipment.id')->all();

        $this->assertEqualsCanonicalizing([$delayed->id, $troubled->id], $ids);
        $this->assertNotContains($fine->id, $ids);
        $this->assertNotContains($resolvedOnly->id, $ids);
        $this->assertNotContains($deliveredLate->id, $ids);
    }

    public function test_the_most_serious_shipment_comes_first_with_a_clear_score(): void
    {
        $watch = $this->late(2);                       // LOW delay = 10
        $high = $this->late(30);                       // HIGH delay = 60
        ShipmentException::factory()->high()->create(['shipment_id' => $high->id]); // +40 +5 (open) = 105
        $medium = $this->late(10);                     // MEDIUM delay = 30

        $queue = app(AttentionService::class)->queue();

        $this->assertSame([$high->id, $medium->id, $watch->id], $queue->pluck('shipment.id')->all());
        $this->assertSame(105, $queue[0]['score']);
        $this->assertSame('CRITICAL', $queue[0]['priority']);
        $this->assertSame(30, $queue[1]['score']);
        $this->assertSame('HIGH', $queue[1]['priority']);
        $this->assertSame('WATCH', $queue[2]['priority']);
    }

    public function test_an_investigating_exception_scores_lower_than_an_open_one(): void
    {
        $a = $this->onTime();
        $b = $this->onTime();
        ShipmentException::factory()->create(['shipment_id' => $a->id]);                     // MEDIUM open = 25
        ShipmentException::factory()->investigating()->create(['shipment_id' => $b->id]);    // MEDIUM investigating = 20

        $queue = app(AttentionService::class)->queue();

        $this->assertSame([$a->id, $b->id], $queue->pluck('shipment.id')->all());
        $this->assertSame([25, 20], $queue->pluck('score')->all());
    }

    public function test_the_queue_page_renders_reasons(): void
    {
        $s = $this->late(30);
        ShipmentException::factory()->high()->create(['shipment_id' => $s->id, 'type' => 'CUSTOMS_HOLD']);

        $this->actingAs(User::factory()->operator()->create())->get(route('attention.index'))
            ->assertOk()
            ->assertSee($s->shipment_number)
            ->assertSee('CRITICAL')
            ->assertSee('Delayed by')
            ->assertSee('Customs hold');
    }

    public function test_an_empty_queue_says_all_clear(): void
    {
        $this->onTime();

        $this->actingAs(User::factory()->operator()->create())->get(route('attention.index'))
            ->assertOk()->assertSee('All clear');
    }

    // ---------- Dashboard ----------

    public function test_the_dashboard_counts_delayed_shipments_and_open_exceptions(): void
    {
        $this->late(3);
        $this->late(40);
        $this->onTime();
        Shipment::factory()->delivered()->create();
        $overdue = Shipment::factory()->create([
            'status' => 'IN_TRANSIT',
            'expected_departure_at' => now()->subDays(20),
            'expected_arrival_at' => now()->subDays(2),
            'estimated_arrival_at' => now()->subDays(2),
        ]);
        ShipmentException::factory()->count(2)->create();
        ShipmentException::factory()->investigating()->create();
        ShipmentException::factory()->resolved()->create();

        $this->actingAs(User::factory()->operator()->create())->get('/dashboard')
            ->assertOk()
            ->assertViewHas('stats', fn ($s) => $s['delayed'] === 3 && $s['open_exceptions'] === 3);

        $this->assertTrue($overdue->delay()->overdue);
    }

    public function test_the_customer_dashboard_counts_only_own_shipments_and_hides_the_queue(): void
    {
        $mine = $this->late(5);
        $this->late(50);
        ShipmentException::factory()->create(['shipment_id' => $mine->id]);
        ShipmentException::factory()->create();

        $this->actingAs($mine->customer)->get('/dashboard')
            ->assertOk()
            ->assertViewHas('stats', fn ($s) => $s['delayed'] === 1 && $s['open_exceptions'] === 1)
            ->assertDontSee('Needs attention');
    }

    // ---------- Settings ----------

    public function test_only_admin_can_open_or_change_settings(): void
    {
        $payload = ['delay_low_max_hours' => 4, 'delay_medium_max_hours' => 12];

        $this->get(route('settings.edit'))->assertRedirect(route('login'));

        foreach ([User::factory()->operator()->create(), User::factory()->customer()->create()] as $user) {
            $this->actingAs($user)->get(route('settings.edit'))->assertForbidden();
            $this->actingAs($user)->put(route('settings.update'), $payload)->assertForbidden();
        }

        $this->assertSame(6, app(EtaService::class)->thresholds()['low_max_hours']);
    }

    public function test_admin_changes_the_thresholds_and_the_rules_follow(): void
    {
        $admin = User::factory()->admin()->create();
        $shipment = $this->late(5); // 5 jam: LOW dengan ambang bawaan 6 jam

        $this->assertSame('LOW', $shipment->delay()->severity);

        $this->actingAs($admin)->get(route('settings.edit'))->assertOk()->assertSee('Delay thresholds');
        $this->actingAs($admin)->put(route('settings.update'), ['delay_low_max_hours' => 4, 'delay_medium_max_hours' => 12])
            ->assertRedirect(route('settings.edit'));

        $this->assertSame(['low_max_hours' => 4, 'medium_max_hours' => 12], app(EtaService::class)->thresholds());
        $this->assertSame('MEDIUM', $shipment->fresh()->delay()->severity); // 5 jam > 4 jam
    }

    public function test_threshold_validation(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('settings.update'), ['delay_low_max_hours' => 10, 'delay_medium_max_hours' => 10])
            ->assertSessionHasErrors('delay_medium_max_hours');
        $this->actingAs($admin)->put(route('settings.update'), ['delay_low_max_hours' => 0, 'delay_medium_max_hours' => 10])
            ->assertSessionHasErrors('delay_low_max_hours');
        $this->actingAs($admin)->put(route('settings.update'), ['delay_low_max_hours' => 'abc', 'delay_medium_max_hours' => 10])
            ->assertSessionHasErrors('delay_low_max_hours');
        $this->actingAs($admin)->put(route('settings.update'), [])
            ->assertSessionHasErrors(['delay_low_max_hours', 'delay_medium_max_hours']);

        $this->assertSame(6, app(EtaService::class)->thresholds()['low_max_hours']);
    }
}
