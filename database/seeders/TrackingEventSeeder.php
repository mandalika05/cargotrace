<?php
/*
 * ============================================================
 * BUAT APA : Riwayat tracking untuk data demo, KONSISTEN dengan status tiap shipment
 *            (syarat brief: data demo sintetis tetapi konsisten dan realistis).
 *   BOOKED     -> event BOOKED
 *   PICKED_UP  -> BOOKED, PICKED_UP
 *   IN_TRANSIT -> BOOKED, PICKED_UP, IN_TRANSIT + titik singgah yang sudah dilewati
 *   DELIVERED  -> semua langkah + DELIVERED di waktu delivered_at
 *   CANCELLED  -> BOOKED lalu CANCELLED
 *   CREATED    -> belum ada event
 * RUTE DEMO  : Asia Tenggara -> Eropa lewat Colombo, Terusan Suez, Selat Gibraltar.
 *            Titik singgah dihitung dari kemajuan waktu perjalanan.
 * created_at shipment dimundurkan sehari sebelum event pertama agar urutan masuk akal.
 * ============================================================
 */

namespace Database\Seeders;

use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TrackingEventSeeder extends Seeder
{
    // Titik singgah dan posisinya (0 = berangkat, 1 = tiba) sepanjang durasi perjalanan
    private const WAYPOINTS = [
        ['name' => 'Colombo, Sri Lanka',    'lat' => 6.9271,  'lng' => 79.8612, 'at' => 0.25],
        ['name' => 'Suez Canal, Egypt',     'lat' => 30.0000, 'lng' => 32.5500, 'at' => 0.55],
        ['name' => 'Strait of Gibraltar',   'lat' => 36.1408, 'lng' => -5.3536, 'at' => 0.80],
    ];

    public function run(): void
    {
        if (TrackingEvent::exists()) {
            return; // jangan dobel kalau seeder dijalankan lagi
        }

        $operatorId = User::where('email', 'operator@cargotrace.test')->value('id');

        Shipment::with(['origin', 'destination', 'carrier'])->orderBy('id')->get()->each(function (Shipment $shipment) use ($operatorId) {
            $events = $this->eventsFor($shipment);
            if ($events === []) {
                return;
            }

            foreach ($events as $data) {
                $event = new TrackingEvent($data);
                $event->forceFill([
                    'shipment_id' => $shipment->id,
                    'created_by' => $operatorId,
                    'created_at' => $data['occurred_at'],
                ])->save();
            }

            Shipment::whereKey($shipment->id)->update(['created_at' => $events[0]['occurred_at']->copy()->subDay()]);
        });
    }

    /** @return array<int, array<string, mixed>> daftar event berurutan waktu */
    private function eventsFor(Shipment $s): array
    {
        $status = $s->status;
        if ($status === Shipment::STATUS_CREATED) {
            return [];
        }

        $dep = $s->expected_departure_at->copy();
        $arr = $s->expected_arrival_at->copy();
        $events = [];

        // 1. BOOKED
        $bookedAt = $dep->copy()->subDays(3);
        if ($status === Shipment::STATUS_BOOKED || $status === Shipment::STATUS_CANCELLED) {
            $bookedAt = $bookedAt->min(now()->subHours(3));
        }
        $events[] = $this->event(Shipment::STATUS_BOOKED, $bookedAt, null, "Booking confirmed with {$s->carrier->name}.");

        if ($status === Shipment::STATUS_BOOKED) {
            return $events;
        }

        if ($status === Shipment::STATUS_CANCELLED) {
            $events[] = $this->event(Shipment::STATUS_CANCELLED, $s->cancelled_at->copy(), null, 'Shipment cancelled at the customer\'s request.');

            return $events;
        }

        // 2. PICKED_UP di asal
        $events[] = $this->event(Shipment::STATUS_PICKED_UP, $dep, $s->origin, 'Cargo picked up and checked in.');
        if ($status === Shipment::STATUS_PICKED_UP) {
            return $events;
        }

        // 3. IN_TRANSIT (berangkat sehari setelah pickup)
        $start = $dep->copy()->addDay();
        $events[] = $this->event(Shipment::STATUS_IN_TRANSIT, $start, $s->origin, 'Departed origin and in transit.');

        // 4. Titik singgah yang sudah dilewati
        $span = max(1, $start->diffInSeconds($arr, false));
        foreach (self::WAYPOINTS as $point) {
            $passedAt = $start->copy()->addSeconds((int) ($span * $point['at']));
            if ($status === Shipment::STATUS_DELIVERED || $passedAt->lte(now()->subHour())) {
                $events[] = $this->event(TrackingEvent::TYPE_LOCATION_UPDATE, $passedAt, null, 'Passed '.$point['name'].'.', $point);
            }
        }

        // 5. DELIVERED
        if ($status === Shipment::STATUS_DELIVERED) {
            $events[] = $this->event(Shipment::STATUS_DELIVERED, $s->delivered_at->copy(), $s->destination, 'Delivered to the consignee.');
        }

        return $events;
    }

    // Menyusun satu baris event. $place bisa model Location atau array titik singgah.
    private function event(string $type, Carbon $at, mixed $place, string $description, ?array $waypoint = null): array
    {
        $data = [
            'event_type' => $type,
            'occurred_at' => $at,
            'description' => $description,
            'location_id' => null,
            'location_name' => null,
            'latitude' => null,
            'longitude' => null,
        ];

        if ($waypoint) {
            $data['location_name'] = $waypoint['name'];
            $data['latitude'] = $waypoint['lat'];
            $data['longitude'] = $waypoint['lng'];
        } elseif ($place) {
            $data['location_id'] = $place->id;
            $data['location_name'] = $place->name;
            $data['latitude'] = $place->latitude;
            $data['longitude'] = $place->longitude;
        }

        return $data;
    }
}
