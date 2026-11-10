<?php
/*
 * ============================================================
 * BUAT APA : Semua LOGIKA BISNIS shipment ada di sini (controller dibuat tipis).
 * POLA DARI: UTS MedikaCare (transaksi dengan rollback), dipakai ulang untuk
 *            "shipment + item barang = satu paket".
 * ISI      :
 *   paginate()       daftar + search + filter + sort + pagination (server-side)
 *   create()         buat shipment + item dalam SATU transaksi
 *   update()         ubah shipment + item (hanya jika belum terminal)
 *   delete()         soft delete
 *   generateNumber() nomor unik CT-20261110-AB12
 * Transisi status (BOOKED, PICKED_UP, ...) ditambahkan di P10.
 * Audit log dan notifikasi dipasang di P13.
 * ============================================================
 */

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShipmentService
{
    /**
     * Daftar shipment untuk halaman index.
     * Customer OTOMATIS hanya melihat miliknya (dibatasi di sini, bukan di tampilan).
     * $filters: q, status, carrier_id, sort (newest|oldest|eta_asc|eta_desc|number)
     */
    public function paginate(User $user, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Shipment::query()->with([
            'customer:id,name',
            'carrier:id,name,code',
            'origin:id,name,city',
            'destination:id,name,city',
        ]);

        if ($user->isCustomer()) {
            $query->where('customer_id', $user->id);
        }

        if (filled($filters['q'] ?? null)) {
            // Karakter khusus LIKE (% dan _) di-escape agar dibaca apa adanya
            $term = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(function ($q) use ($term, $user) {
                $q->where('shipment_number', 'like', $term);
                if (! $user->isCustomer()) {
                    $q->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term));
                }
            });
        }

        if (filled($filters['status'] ?? null) && in_array($filters['status'], Shipment::STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['carrier_id'] ?? null)) {
            $query->where('carrier_id', (int) $filters['carrier_id']);
        }

        // Pilihan urutan dibatasi daftar putih (anti input kolom sembarang)
        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest('id'),
            'eta_asc' => $query->orderBy('estimated_arrival_at'),
            'eta_desc' => $query->orderByDesc('estimated_arrival_at'),
            'number' => $query->orderBy('shipment_number'),
            default => $query->latest('id'),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Buat shipment + semua item sebagai satu paket.
     * DB::transaction: kalau SATU item gagal disimpan, semuanya dibatalkan (rollback),
     * jadi tidak ada shipment "setengah jadi".
     */
    public function create(array $data, array $items, User $actor): Shipment
    {
        return DB::transaction(function () use ($data, $items, $actor) {
            $shipment = new Shipment($data); // hanya kolom $fillable yang masuk

            // Kalau ETA belum diisi, awalnya sama dengan jadwal tiba
            if (empty($shipment->estimated_arrival_at)) {
                $shipment->estimated_arrival_at = $shipment->expected_arrival_at;
            }

            // Kolom yang TIDAK boleh dari input diisi eksplisit oleh Service
            $shipment->forceFill([
                'shipment_number' => $this->generateNumber(),
                'status' => Shipment::STATUS_CREATED,
                'created_by' => $actor->id,
            ]);
            $shipment->save();

            $this->syncItems($shipment, $items);

            return $shipment;
        });
    }

    /**
     * Ubah data shipment dan daftar itemnya (dalam transaksi).
     * Status tidak ikut berubah di sini (itu tugas transisi status, P10).
     */
    public function update(Shipment $shipment, array $data, array $items): Shipment
    {
        // Penjaga kedua. Penjaga pertama = ShipmentPolicy.
        if ($shipment->isTerminal()) {
            throw new DomainException('A delivered or cancelled shipment can no longer be edited.');
        }

        return DB::transaction(function () use ($shipment, $data, $items) {
            $shipment->fill($data);
            if (empty($shipment->estimated_arrival_at)) {
                $shipment->estimated_arrival_at = $shipment->expected_arrival_at;
            }
            $shipment->save();

            $this->syncItems($shipment, $items);

            return $shipment;
        });
    }

    // Soft delete: hanya diberi tanda deleted_at
    public function delete(Shipment $shipment): void
    {
        $shipment->delete();
    }

    // Nomor unik, mis. CT-20261110-7KQ2. Dicek ke database (termasuk yang sudah dihapus).
    public function generateNumber(): string
    {
        do {
            $number = 'CT-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));
        } while (Shipment::withTrashed()->where('shipment_number', $number)->exists());

        return $number;
    }

    // Ganti seluruh item dengan daftar baru
    private function syncItems(Shipment $shipment, array $items): void
    {
        $shipment->items()->delete();

        foreach ($items as $item) {
            $shipment->items()->create([
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
                'weight_kg' => $item['weight_kg'] ?? null,
            ]);
        }
    }
}
