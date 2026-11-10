<?php
/*
 * ============================================================
 * BUAT APA : Menguji angka dashboard: harus dari database dan
 *            customer hanya menghitung shipment miliknya.
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_come_from_the_database(): void
    {
        Shipment::factory()->count(2)->create(['status' => 'IN_TRANSIT']);
        Shipment::factory()->count(3)->create(['status' => 'BOOKED']);
        Shipment::factory()->delivered()->create();

        $response = $this->actingAs(User::factory()->operator()->create())->get('/dashboard');

        $response->assertOk()->assertViewHas('stats', fn ($s) => $s['total'] === 6
            && $s['in_transit'] === 2 && $s['pending'] === 3 && $s['delivered'] === 1);
    }

    public function test_customer_dashboard_counts_only_own_shipments(): void
    {
        $mine = Shipment::factory()->create(['status' => 'IN_TRANSIT']);
        Shipment::factory()->count(4)->create(['status' => 'IN_TRANSIT']);

        $this->actingAs($mine->customer)->get('/dashboard')
            ->assertOk()
            ->assertViewHas('stats', fn ($s) => $s['total'] === 1 && $s['in_transit'] === 1);
    }
}
