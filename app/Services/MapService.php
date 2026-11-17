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
use App\Models\User;

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
     * PETA DASHBOARD: posisi terkini semua shipment yang MASIH BERJALAN (kartu "Shipment Map").
     * Posisi = titik tracking terbaru yang punya koordinat; bila belum ada, lokasi asal.
     * Customer hanya melihat shipment miliknya. Maksimal $limit shipment terbaru.
     *
     * @return array<string, mixed>
     */
    public function overview(User $user, int $limit = 100): array
    {
        $query = Shipment::query()
            ->whereNotIn('status', Shipment::TERMINAL_STATUSES)
            ->with(['origin', 'destination'])
            ->orderByDesc('id')
            ->limit($limit);

        if ($user->isCustomer()) {
            $query->where('customer_id', $user->id);
        }

        $shipments = $query->get();

        // Semua event sekali jalan (bukan satu query per shipment), lalu dikelompokkan
        $events = TrackingEvent::query()
            ->whereIn('shipment_id', $shipments->pluck('id'))
            ->whereNotIn('event_type', TrackingEvent::MARKER_TYPES)
            ->with('location:id,latitude,longitude')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->groupBy('shipment_id');

        $points = [];
        foreach ($shipments as $shipment) {
            $coords = null;
            $source = 'current';

            foreach (($events[$shipment->id] ?? collect())->reverse() as $event) {
                $coords = $this->eventCoordinates($event);
                if ($coords !== null) {
                    break;
                }
            }

            if ($coords === null && $shipment->origin && $shipment->origin->latitude !== null && $shipment->origin->longitude !== null) {
                $coords = [(float) $shipment->origin->latitude, (float) $shipment->origin->longitude];
                $source = 'origin';
            }

            if ($coords === null) {
                continue; // tidak ada koordinat sama sekali
            }

            $points[] = [
                'number' => $shipment->shipment_number,
                'status' => $shipment->statusLabel(),
                'route' => ($shipment->origin?->city ?? '-').' -> '.($shipment->destination?->city ?? '-'),
                'url' => route('shipments.show', $shipment),
                'delayed' => $shipment->delay()->isDelayed(),
                'source' => $source,
                'latitude' => $coords[0],
                'longitude' => $coords[1],
            ];
        }

        return [
            'available' => $points !== [],
            'message' => $points === [] ? 'No running shipment has a known position yet.' : null,
            'points' => $points,
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
