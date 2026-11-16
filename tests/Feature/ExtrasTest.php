<?php
/*
 * ============================================================
 * BUAT APA : Menguji fitur tambahan P11.4: prakiraan cuaca (Open-Meteo, dipalsukan),
 *            halaman lacak publik, dan export CSV.
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExtrasTest extends TestCase
{
    use RefreshDatabase;

    private function fakeForecast(int $code = 1, float $rain = 0, float $wind = 10): void
    {
        Http::fake(['*' => Http::response(['daily' => [
            'time' => ['2026-11-16', '2026-11-17'],
            'weather_code' => [$code, $code],
            'temperature_2m_max' => [30, 31],
            'temperature_2m_min' => [24, 25],
            'precipitation_sum' => [$rain, $rain],
            'wind_speed_10m_max' => [$wind, $wind],
        ]], 200)]);
    }

    // ---------- Cuaca ----------

    public function test_risk_levels_follow_the_thresholds(): void
    {
        $w = new WeatherService();
        $this->assertSame('LOW', $w->risk(1, 2, 10));
        $this->assertSame('MEDIUM', $w->risk(1, 20, 10));
        $this->assertSame('MEDIUM', $w->risk(1, 0, 45));
        $this->assertSame('HIGH', $w->risk(95, 0, 10));
        $this->assertSame('HIGH', $w->risk(1, 0, 70));
    }

    public function test_forecast_is_parsed_and_cached(): void
    {
        Cache::flush();
        $this->fakeForecast();
        $w = new WeatherService();

        $first = $w->forecast(1.23, 103.8);
        $this->assertTrue($first['available']);
        $this->assertCount(2, $first['days']);

        $w->forecast(1.23, 103.8);
        Http::assertSentCount(1); // panggilan kedua dari cache
    }

    public function test_forecast_fails_gracefully_when_offline(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response([], 500)]);

        $this->assertFalse((new WeatherService())->forecast(5.5, 100.1)['available']);
    }

    public function test_weather_endpoint_returns_json_for_an_active_shipment(): void
    {
        Cache::flush();
        $this->fakeForecast();
        $user = User::factory()->operator()->create();
        $shipment = Shipment::factory()->create(['status' => 'IN_TRANSIT']);

        $this->actingAs($user)->getJson(route('shipments.weather', $shipment))
            ->assertOk()->assertJsonStructure(['data' => ['applicable']]);
    }

    public function test_weather_is_not_applicable_for_a_delivered_shipment(): void
    {
        $user = User::factory()->operator()->create();
        $shipment = Shipment::factory()->create(['status' => 'DELIVERED', 'delivered_at' => now()]);

        $this->actingAs($user)->getJson(route('shipments.weather', $shipment))
            ->assertOk()->assertJsonPath('data.applicable', false);
    }

    public function test_weather_requires_login_and_respects_ownership(): void
    {
        $shipment = Shipment::factory()->create(['status' => 'IN_TRANSIT']);
        $this->getJson(route('shipments.weather', $shipment))->assertUnauthorized();

        $other = User::factory()->customer()->create();
        $this->actingAs($other)->getJson(route('shipments.weather', $shipment))->assertForbidden();
    }

    // ---------- Lacak publik ----------

    public function test_public_tracking_page_loads_without_login(): void
    {
        $this->get(route('track'))->assertOk()->assertSee('Track your shipment');
    }

    public function test_public_tracking_finds_a_shipment_case_insensitively_without_leaking_private_data(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Rahasia Pelanggan']);
        $shipment = Shipment::factory()->create(['customer_id' => $customer->id, 'shipment_number' => 'CT-99887766-abcd']);

        $this->get(route('track', ['number' => 'ct-99887766-ABCD']))
            ->assertOk()
            ->assertSee('CT-99887766-abcd')
            ->assertDontSee('Rahasia Pelanggan');
    }

    public function test_public_tracking_shows_not_found_and_rejects_odd_input(): void
    {
        $this->get(route('track', ['number' => 'CT-00000000-zzzz']))->assertOk()->assertSee('No shipment found');
        $this->get(route('track', ['number' => "'; DROP TABLE--"]))->assertOk()->assertSee('Use only letters, numbers and dashes');
    }

    // ---------- Export CSV ----------

    public function test_csv_export_requires_login(): void
    {
        $this->get(route('exports.shipments'))->assertRedirect(route('login'));
    }

    public function test_csv_export_is_scoped_to_what_the_user_may_see(): void
    {
        $customer = User::factory()->customer()->create();
        Shipment::factory()->create(['customer_id' => $customer->id, 'shipment_number' => 'CT-11111111-mine']);
        Shipment::factory()->create(['shipment_number' => 'CT-22222222-othr']);

        $csv = $this->actingAs($customer)->get(route('exports.shipments'))->assertOk()->streamedContent();

        $this->assertStringContainsString('CT-11111111-mine', $csv);
        $this->assertStringNotContainsString('CT-22222222-othr', $csv);
    }

    public function test_csv_export_neutralises_formula_injection(): void
    {
        $staff = User::factory()->operator()->create();
        $evil = User::factory()->customer()->create(['name' => '=HYPERLINK("http://x")']);
        Shipment::factory()->create(['customer_id' => $evil->id]);

        $csv = $this->actingAs($staff)->get(route('exports.shipments'))->streamedContent();

        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }
}
