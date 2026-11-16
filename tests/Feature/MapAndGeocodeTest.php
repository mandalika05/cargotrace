<?php
/*
 * ============================================================
 * BUAT APA : Menguji P12: data peta shipment (MapService + endpoint) dan geocoding
 *            (Nominatim dipalsukan dengan Http::fake, tidak memakai internet sungguhan).
 * ISI TES  : titik asal/tujuan/tracking, event tanpa koordinat dilewati, event penanda tidak
 *            ikut, hak akses customer (403), geocoding sukses/kosong/gagal/cache,
 *            geocoding hanya untuk staf, validasi input.
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\GeocodingService;
use App\Services\MapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapAndGeocodeTest extends TestCase
{
    use RefreshDatabase;

    private function shipmentWithCoordinates(array $attributes = []): Shipment
    {
        $origin = Location::factory()->create(['latitude' => 1.2900000, 'longitude' => 103.8500000]);
        $destination = Location::factory()->create(['latitude' => 51.9500000, 'longitude' => 4.1400000]);

        return Shipment::factory()->create(array_merge([
            'status' => 'IN_TRANSIT',
            'origin_id' => $origin->id,
            'destination_id' => $destination->id,
        ], $attributes));
    }

    private function addEvent(Shipment $shipment, array $attrs): TrackingEvent
    {
        return $shipment->trackingEvents()->forceCreate(array_merge([
            'shipment_id' => $shipment->id,
            'event_type' => TrackingEvent::TYPE_LOCATION_UPDATE,
            'occurred_at' => now()->subHours(5),
        ], $attrs));
    }

    // ---------- Peta ----------

    public function test_map_has_origin_destination_and_tracking_points_in_time_order(): void
    {
        $s = $this->shipmentWithCoordinates();
        $this->addEvent($s, ['latitude' => 6.9, 'longitude' => 79.8, 'location_name' => 'Colombo', 'occurred_at' => now()->subHours(10)]);
        $this->addEvent($s, ['latitude' => 12.5, 'longitude' => 43.3, 'location_name' => 'Gulf of Aden', 'occurred_at' => now()->subHours(2)]);

        $data = app(MapService::class)->forShipment($s);

        $this->assertTrue($data['available']);
        $this->assertSame(1.29, $data['origin']['latitude']);
        $this->assertSame(51.95, $data['destination']['latitude']);
        $this->assertCount(2, $data['points']);
        $this->assertSame('Colombo', $data['points'][0]['place']);
        $this->assertSame('Gulf of Aden', $data['current']['place']);
        $this->assertCount(3, $data['route']);      // asal + 2 titik
        $this->assertCount(2, $data['remaining']);  // posisi terakhir -> tujuan
    }

    public function test_events_without_coordinates_and_marker_events_are_skipped(): void
    {
        $s = $this->shipmentWithCoordinates();
        $this->addEvent($s, ['location_name' => 'Somewhere at sea']);                              // tanpa koordinat
        $this->addEvent($s, ['event_type' => TrackingEvent::TYPE_EXCEPTION, 'latitude' => 9, 'longitude' => 9]); // penanda
        $this->addEvent($s, ['latitude' => 5.5, 'longitude' => 100.1]);

        $data = app(MapService::class)->forShipment($s);

        $this->assertCount(1, $data['points']);
    }

    public function test_event_falls_back_to_the_chosen_location_coordinates(): void
    {
        $s = $this->shipmentWithCoordinates();
        $port = Location::factory()->create(['latitude' => 25.0, 'longitude' => 55.0]);
        $this->addEvent($s, ['location_id' => $port->id]);

        $data = app(MapService::class)->forShipment($s);

        $this->assertSame(25.0, $data['points'][0]['latitude']);
    }

    public function test_no_remaining_line_for_a_delivered_shipment(): void
    {
        // Status tidak ada di $fillable (proteksi), jadi dibuat langsung lewat factory
        $s = $this->shipmentWithCoordinates(['status' => 'DELIVERED', 'delivered_at' => now()]);
        $this->addEvent($s, ['latitude' => 5.5, 'longitude' => 100.1]);

        $this->assertSame([], app(MapService::class)->forShipment($s)['remaining']);
    }

    public function test_map_is_not_available_when_nothing_has_coordinates(): void
    {
        $origin = Location::factory()->create(['latitude' => null, 'longitude' => null]);
        $destination = Location::factory()->create(['latitude' => null, 'longitude' => null]);
        $s = Shipment::factory()->create(['origin_id' => $origin->id, 'destination_id' => $destination->id]);

        $data = app(MapService::class)->forShipment($s);

        $this->assertFalse($data['available']);
        $this->assertNotEmpty($data['message']);
    }

    public function test_map_endpoint_returns_json_and_respects_ownership(): void
    {
        $s = $this->shipmentWithCoordinates();

        $this->getJson(route('shipments.map', $s))->assertUnauthorized();

        $operator = User::factory()->operator()->create();
        $this->actingAs($operator)->getJson(route('shipments.map', $s))
            ->assertOk()->assertJsonPath('data.available', true);

        $owner = User::find($s->customer_id);
        $this->actingAs($owner)->getJson(route('shipments.map', $s))->assertOk();

        $other = User::factory()->customer()->create();
        $this->actingAs($other)->getJson(route('shipments.map', $s))->assertForbidden();
    }

    public function test_shipment_page_contains_the_map_card(): void
    {
        $s = $this->shipmentWithCoordinates();
        $operator = User::factory()->operator()->create();

        $this->actingAs($operator)->get(route('shipments.show', $s))
            ->assertOk()->assertSee('shipment-map', false)->assertSee('Route map');
    }

    // ---------- Geocoding ----------

    private function fakeNominatim(): void
    {
        Http::fake(['*' => Http::response([
            ['lat' => '51.9225', 'lon' => '4.47917', 'display_name' => 'Rotterdam, South Holland, Netherlands'],
            ['display_name' => 'row without coordinates'],
        ], 200)]);
    }

    public function test_geocoding_returns_results_and_skips_broken_rows(): void
    {
        Cache::flush();
        $this->fakeNominatim();

        $out = (new GeocodingService())->search('Rotterdam');

        $this->assertTrue($out['available']);
        $this->assertCount(1, $out['results']);
        $this->assertSame(51.9225, $out['results'][0]['latitude']);
    }

    public function test_geocoding_sends_a_user_agent_and_caches_results(): void
    {
        Cache::flush();
        $this->fakeNominatim();
        $service = new GeocodingService();

        $service->search('Rotterdam');
        $service->search('  rotterdam ');   // sama setelah dirapikan -> dari cache

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->hasHeader('User-Agent'));
    }

    public function test_geocoding_handles_a_service_error(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response([], 500)]);

        $out = (new GeocodingService())->search('Nowhere land');

        $this->assertFalse($out['available']);
        $this->assertSame([], $out['results']);
    }

    public function test_geocoding_handles_empty_data(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response([], 200)]);

        $out = (new GeocodingService())->search('Zzzzzzzz');

        $this->assertTrue($out['available']);
        $this->assertSame([], $out['results']);
    }

    public function test_geocode_endpoint_is_for_staff_only_and_validates_input(): void
    {
        Cache::flush();
        $this->fakeNominatim();

        $this->getJson(route('geocode', ['q' => 'Rotterdam']))->assertUnauthorized();

        $customer = User::factory()->customer()->create();
        $this->actingAs($customer)->getJson(route('geocode', ['q' => 'Rotterdam']))->assertForbidden();

        $operator = User::factory()->operator()->create();
        $this->actingAs($operator)->getJson(route('geocode', ['q' => 'Rotterdam']))
            ->assertOk()->assertJsonPath('data.results.0.latitude', 51.9225);

        $this->actingAs($operator)->getJson(route('geocode', ['q' => 'ab']))->assertUnprocessable();
        $this->actingAs($operator)->getJson(route('geocode'))->assertUnprocessable();
    }

    public function test_forms_show_the_find_coordinates_button(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('locations.create'))->assertOk()->assertSee('data-geocode', false);
    }
}
