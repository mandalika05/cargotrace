<?php
/*
 * ============================================================
 * BUAT APA : Logika bisnis tracking: menambah event dan (khusus Admin) membuka
 *            kembali shipment terminal. Controller dan API (P11) memakai ini.
 *
 * addEvent() menjaga 5 aturan, SEMUANYA di dalam satu transaksi:
 *   1. shipment belum terminal
 *   2. waktu kejadian tidak di masa depan
 *   3. waktu tidak mundur dari event sebelumnya
 *   4. perubahan status mengikuti ShipmentStateMachine
 *   5. LOCATION_UPDATE hanya saat PICKED_UP / IN_TRANSIT
 * Pelanggaran -> ValidationException (web: kembali ke form dengan pesan;
 * API: respons 422).
 *
 * lockForUpdate(): shipment dikunci selama transaksi supaya dua operator yang
 * menambah event bersamaan tidak saling menimpa urutan/status.
 * ============================================================
 */

namespace App\Services;

use App\Models\Location;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrackingService
{
    public function __construct(private readonly ShipmentStateMachine $machine)
    {
    }

    public function addEvent(Shipment $shipment, array $data, User $actor): TrackingEvent
    {
        return DB::transaction(function () use ($shipment, $data, $actor) {
            $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            // 1. Shipment terminal tidak menerima event baru
            if ($shipment->isTerminal()) {
                $this->fail('event_type', 'This shipment is '.$shipment->statusLabel().' and no longer accepts events.');
            }

            $type = $data['event_type'];
            $occurredAt = Carbon::parse($data['occurred_at']);

            // 2. Tidak di masa depan
            if ($occurredAt->isFuture()) {
                $this->fail('occurred_at', 'Event time cannot be in the future.');
            }

            // 3. Tidak mundur dari event terakhir
            $last = $shipment->trackingEvents()->orderByDesc('occurred_at')->orderByDesc('id')->first();
            if ($last && $occurredAt->lt($last->occurred_at)) {
                $this->fail('occurred_at', 'Event time cannot be earlier than the previous event ('.$last->occurred_at->format('d M Y, H:i').').');
            }

            // 4 & 5. Jenis event
            $isStatusChange = in_array($type, Shipment::STATUSES, true);
            if ($isStatusChange) {
                if (! $this->machine->canTransition($shipment->status, $type)) {
                    $this->fail('event_type', "Status cannot change from {$shipment->statusLabel()} to ".str_replace('_', ' ', $type).'.');
                }
            } elseif ($type === TrackingEvent::TYPE_LOCATION_UPDATE) {
                if (! in_array($shipment->status, [Shipment::STATUS_PICKED_UP, Shipment::STATUS_IN_TRANSIT], true)) {
                    $this->fail('event_type', 'A location update is only possible while the shipment is picked up or in transit.');
                }
            } else {
                $this->fail('event_type', 'Unknown event type.');
            }

            // Kalau memilih lokasi dari daftar, nama dan koordinatnya disalin
            if (! empty($data['location_id'])) {
                $location = Location::find($data['location_id']);
                $data['location_name'] = $data['location_name'] ?? $location?->name;
                $data['latitude'] = $data['latitude'] ?? $location?->latitude;
                $data['longitude'] = $data['longitude'] ?? $location?->longitude;
            }

            $event = new TrackingEvent([
                'event_type' => $type,
                'location_id' => $data['location_id'] ?? null,
                'location_name' => $data['location_name'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'occurred_at' => $occurredAt,
                'description' => $data['description'] ?? null,
            ]);
            $event->forceFill(['shipment_id' => $shipment->id, 'created_by' => $actor->id])->save();

            // Perubahan status ikut dicatat di shipment
            if ($isStatusChange) {
                $shipment->forceFill(['status' => $type]);
                if ($type === Shipment::STATUS_DELIVERED) {
                    $shipment->delivered_at = $occurredAt;
                    $shipment->estimated_arrival_at = $occurredAt; // ETA menjadi waktu tiba sebenarnya
                }
                if ($type === Shipment::STATUS_CANCELLED) {
                    $shipment->cancelled_at = $occurredAt;
                }
                $shipment->save();
            }

            return $event;
        });
    }

    /**
     * PROSEDUR KHUSUS ADMIN: membuka kembali shipment DELIVERED / CANCELLED.
     * Wajib ada alasan. Tercatat sebagai event REOPENED di timeline (riwayat tidak
     * dihapus). Audit log umum ditambahkan di P13.
     */
    public function reopen(Shipment $shipment, User $actor, string $reason): TrackingEvent
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Only an administrator can reopen a shipment.');
        }

        return DB::transaction(function () use ($shipment, $actor, $reason) {
            $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            $target = $this->machine->reopenTarget((string) $shipment->status);
            if (! $shipment->isTerminal() || $target === null) {
                $this->fail('reason', 'Only a delivered or cancelled shipment can be reopened.');
            }

            $last = $shipment->trackingEvents()->orderByDesc('occurred_at')->orderByDesc('id')->first();
            $at = $last && $last->occurred_at->gt(now()) ? $last->occurred_at : now();

            $event = new TrackingEvent([
                'event_type' => TrackingEvent::TYPE_REOPENED,
                'occurred_at' => $at,
                'description' => "Reopened by {$actor->name}. Reason: {$reason}. Status set to ".str_replace('_', ' ', $target).'.',
            ]);
            $event->forceFill(['shipment_id' => $shipment->id, 'created_by' => $actor->id])->save();

            $shipment->forceFill([
                'status' => $target,
                'delivered_at' => null,
                'cancelled_at' => null,
            ]);
            if ($shipment->expected_arrival_at) {
                $shipment->estimated_arrival_at = $shipment->expected_arrival_at;
            }
            $shipment->save();

            return $event;
        });
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
