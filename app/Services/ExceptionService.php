<?php
/*
 * ============================================================
 * BUAT APA : Logika bisnis EXCEPTION (gangguan shipment). Controller web dan API (P11)
 *            sama-sama memakai ini, jadi aturannya cuma ditulis SEKALI.
 *
 * report()        catat exception baru (status awal OPEN) + penanda di timeline
 * changeStatus()  OPEN -> INVESTIGATING -> RESOLVED (aturan di ShipmentException::TRANSITIONS)
 * visibleTo()     query exception yang BOLEH dilihat user (customer: hanya miliknya)
 * paginate()      daftar + filter + urutan paling gawat dulu
 * summary()       angka ringkas (open / investigating / resolved) untuk dashboard
 *
 * ATURAN   :
 *   - Hanya staf (admin / operator) yang mencatat dan mengubah. Customer hanya melihat.
 *   - Tidak bisa mencatat exception pada shipment CANCELLED.
 *   - Waktu terdeteksi tidak boleh di masa depan.
 *   - RESOLVED wajib punya catatan penyelesaian dan tidak bisa dibuka lagi.
 *   - Setiap langkah memakai DB::transaction + lockForUpdate (dua operator yang
 *     menekan tombol bersamaan tidak saling menimpa).
 *   - Setiap langkah tercatat di timeline shipment (event penanda, riwayat tidak dihapus).
 * ============================================================
 */

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExceptionService
{
    /**
     * @param array{type:string, severity:string, description?:?string, detected_at?:mixed} $data
     */
    public function report(Shipment $shipment, array $data, User $actor): ShipmentException
    {
        $this->assertStaff($actor);

        return DB::transaction(function () use ($shipment, $data, $actor) {
            $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if ($shipment->status === Shipment::STATUS_CANCELLED) {
                $this->fail('type', 'An exception cannot be reported on a cancelled shipment.');
            }

            $type = (string) ($data['type'] ?? '');
            $severity = (string) ($data['severity'] ?? '');
            if (! in_array($type, ShipmentException::TYPES, true)) {
                $this->fail('type', 'Unknown exception type.');
            }
            if (! in_array($severity, ShipmentException::SEVERITIES, true)) {
                $this->fail('severity', 'Severity must be LOW, MEDIUM or HIGH.');
            }

            $detectedAt = filled($data['detected_at'] ?? null) ? Carbon::parse($data['detected_at']) : now();
            if ($detectedAt->isFuture()) {
                $this->fail('detected_at', 'Detection time cannot be in the future.');
            }

            $exception = new ShipmentException([
                'type' => $type,
                'severity' => $severity,
                'description' => $data['description'] ?? null,
            ]);
            $exception->forceFill([
                'shipment_id' => $shipment->id,
                'status' => ShipmentException::STATUS_OPEN,
                'detected_at' => $detectedAt,
                'reported_by' => $actor->id,
            ])->save();

            $this->marker(
                $shipment, TrackingEvent::TYPE_EXCEPTION, $detectedAt, $actor,
                'Exception reported: '.ShipmentException::typeLabel($type).' ('.$severity.').'
                    .($exception->description ? ' '.$exception->description : '')
            );

            return $exception;
        });
    }

    // Mengubah status exception: OPEN -> INVESTIGATING -> RESOLVED (aturan di ShipmentException::TRANSITIONS).
    // Hanya staf. Dalam satu transaksi dengan kunci baris (lockForUpdate) supaya dua orang tidak saling menimpa.
    // Saat RESOLVED wajib ada catatan penyelesaian (min. 5 huruf); waktu dan penyelesainya dicatat.
    // Setiap perubahan ditulis ke timeline shipment sebagai event penanda.
    public function changeStatus(ShipmentException $exception, string $to, User $actor, ?string $note = null): ShipmentException
    {
        $this->assertStaff($actor);

        return DB::transaction(function () use ($exception, $to, $actor, $note) {
            $exception = ShipmentException::whereKey($exception->id)->lockForUpdate()->firstOrFail();
            $shipment = Shipment::withTrashed()->findOrFail($exception->shipment_id);

            if (! in_array($to, $exception->allowedNextStatuses(), true)) {
                $this->fail('status', 'An exception cannot change from '.$exception->statusLabel().' to '.ucfirst(strtolower($to)).'.');
            }

            $note = filled($note) ? trim((string) $note) : null;

            if ($to === ShipmentException::STATUS_RESOLVED) {
                if ($note === null || mb_strlen($note) < 5) {
                    $this->fail('resolution_note', 'Please describe how the exception was resolved (at least 5 characters).');
                }
                $exception->forceFill([
                    'status' => $to,
                    'resolved_at' => now(),
                    'resolved_by' => $actor->id,
                    'resolution_note' => $note,
                ])->save();

                $this->marker($shipment, TrackingEvent::TYPE_EXCEPTION_RESOLVED, now(), $actor,
                    ShipmentException::typeLabel($exception->type).' resolved. '.$note);
            } else {
                $exception->forceFill(['status' => $to])->save();

                $this->marker($shipment, TrackingEvent::TYPE_EXCEPTION_UPDATE, now(), $actor,
                    ShipmentException::typeLabel($exception->type).' is now being investigated.'.($note ? ' '.$note : ''));
            }

            return $exception;
        });
    }

    // Exception yang boleh dilihat user. Customer: hanya pada shipment miliknya.
    public function visibleTo(User $user): Builder
    {
        $query = ShipmentException::query()->whereHas('shipment'); // shipment yang dihapus tidak ikut

        if ($user->isCustomer()) {
            $query->whereHas('shipment', fn ($q) => $q->where('customer_id', $user->id));
        }

        return $query;
    }

    /**
     * Daftar exception. Filter: status, severity, type, q (nomor shipment).
     * Urutan: belum selesai dulu, lalu paling gawat, lalu terbaru.
     */
    public function paginate(User $user, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->visibleTo($user)->with([
            'shipment:id,shipment_number,customer_id,origin_id,destination_id,status',
            'shipment.origin:id,city',
            'shipment.destination:id,city',
            'shipment.customer:id,name',
        ]);

        if (filled($filters['status'] ?? null) && in_array($filters['status'], ShipmentException::STATUSES, true)) {
            $query->where('status', $filters['status']);
        }
        if (filled($filters['severity'] ?? null) && in_array($filters['severity'], ShipmentException::SEVERITIES, true)) {
            $query->where('severity', $filters['severity']);
        }
        if (filled($filters['type'] ?? null) && in_array($filters['type'], ShipmentException::TYPES, true)) {
            $query->where('type', $filters['type']);
        }
        if (filled($filters['q'] ?? null)) {
            $term = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->whereHas('shipment', fn ($q) => $q->where('shipment_number', 'like', $term));
        }

        $query
            ->orderByRaw("CASE status WHEN 'OPEN' THEN 0 WHEN 'INVESTIGATING' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE severity WHEN 'HIGH' THEN 0 WHEN 'MEDIUM' THEN 1 ELSE 2 END")
            ->orderByDesc('detected_at')
            ->orderByDesc('id');

        return $query->paginate($perPage)->withQueryString();
    }

    /** @return array{open:int, investigating:int, resolved:int, unresolved:int} */
    public function summary(User $user): array
    {
        $counts = $this->visibleTo($user)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $open = (int) ($counts[ShipmentException::STATUS_OPEN] ?? 0);
        $investigating = (int) ($counts[ShipmentException::STATUS_INVESTIGATING] ?? 0);

        return [
            'open' => $open,
            'investigating' => $investigating,
            'resolved' => (int) ($counts[ShipmentException::STATUS_RESOLVED] ?? 0),
            'unresolved' => $open + $investigating,
        ];
    }

    // ---------- Pembantu ----------

    private function marker(Shipment $shipment, string $type, Carbon $at, User $actor, string $description): void
    {
        $event = new TrackingEvent(['event_type' => $type, 'occurred_at' => $at, 'description' => $description]);
        $event->forceFill(['shipment_id' => $shipment->id, 'created_by' => $actor->id])->save();
    }

    // Penjaga kedua (setelah Policy): menolak kalau pengguna bukan Admin/Operator.
    private function assertStaff(User $actor): void
    {
        if (! $actor->hasRole(User::ROLE_ADMIN, User::ROLE_OPERATOR)) {
            throw new AuthorizationException('Only staff can manage exceptions.');
        }
    }

    // Melempar error validasi untuk satu kolom (web: kembali ke form dengan pesan; API: respons 422).
    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
