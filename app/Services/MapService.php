<?php
/*
 * ============================================================
 * BUAT APA : Menyiapkan data PETA untuk satu shipment (syarat brief: marker origin,
 *            destination, dan titik tracking, dengan popup status/waktu/lokasi).
 * HASIL    : forShipment() mengembalikan array siap jadi JSON:
 *            - origin / destination : titik dari tabel locations
 *            - points               : titik tracking berurutan waktu (event biasa saja;
 *                                     event penanda seperti EXCEPTION tidak ikut)
 *            - current              : titik tracking terbaru yang valid
 *            - route / remaining    : garis yang sudah dilalui, dan garis sisa ke tujuan
 * KOORDINAT: event memakai latitude/longitude miliknya; kalau kosong, memakai koordinat
 *            lokasi yang dipilih (location_id). Event tanpa koordinat dilewati.
 * AMAN     : nama tempat dikirim sebagai teks biasa; JavaScript memasangnya dengan
 *            textContent, jadi tidak bisa menyisipkan HTML (XSS).
 * ============================================================
 */

namespace App\Services;

use App\Models\Location;
use App\Models\Shipment;
use App\Models\TrackingEvent;

class MapService
{
    /**
     * @return array<string, mixed>
     */
    public function forShipment(Shipment $shipment): array
    {
        $shipment->loadMissing(['origin', 'destination']);

        $origin = $this->locationPoint($shipment->origin, 'origin');
        $destination = $this->locationPoint($shipment->destination, 'destination');

        $events = $shipment->trackingEvents()
            ->whereNotIn('event_type', TrackingEvent::MARKER_TYPES)
            ->with('location:id,name,latitude,longitude')
            ->chronological()
            ->get();

        $points = [];
        foreach ($events as $event) {
            $coords = $this->eventCoordinates($event);
            if ($coords === null) {
                continue; // tidak ada koordinat -> tidak bisa digambar
            }

            $points[] = [
                'kind' => 'event',
                'title' => $event->label(),
                'place' => $event->placeLabel(),
                'time' => $event->occurred_at->format('d M Y, H:i'),
                'description' => $event->description,
                'latitude' => $coords[0],
                'longitude' => $coords[1],
            ];
        }

        $current = $points === [] ? null : $points[array_key_last($points)];

        // Garis yang sudah dilalui: asal -> semua titik tracking
        $route = [];
        if ($origin) {
            $route[] = [$origin['latitude'], $origin['longitude']];
        }
        foreach ($points as $p) {
            $route[] = [$p['latitude'], $p['longitude']];
        }

        // Garis sisa (putus-putus): posisi terakhir -> tujuan, hanya selama shipment berjalan
        $remaining = [];
        if (! $shipment->isTerminal() && $destination && $route !== []) {
            $remaining = [end($route), [$destination['latitude'], $destination['longitude']]];
        }

        $hasAny = $origin || $destination || $points !== [];

        return [
            'available' => (bool) $hasAny,
            'message' => $hasAny ? null : 'No coordinates are known for this shipment yet. Add latitude/longitude to the locations or tracking events.',
            'status' => $shipment->status,
            'origin' => $origin,
            'destination' => $destination,
            'points' => $points,
            'current' => $current,
            'route' => $route,
            'remaining' => $remaining,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function locationPoint(?Location $location, string $kind): ?array
    {
        if (! $location || $location->latitude === null || $location->longitude === null) {
            return null;
        }

        return [
            'kind' => $kind,
            'title' => $kind === 'origin' ? 'Origin' : 'Destination',
            'place' => trim($location->name.', '.$location->city.', '.$location->country, ', '),
            'latitude' => (float) $location->latitude,
            'longitude' => (float) $location->longitude,
        ];
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function eventCoordinates(TrackingEvent $event): ?array
    {
        if ($event->latitude !== null && $event->longitude !== null) {
            return [(float) $event->latitude, (float) $event->longitude];
        }

        $location = $event->location;
        if ($location && $location->latitude !== null && $location->longitude !== null) {
            return [(float) $location->latitude, (float) $location->longitude];
        }

        return null;
    }
}
