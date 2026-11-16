<?php
/*
 * ============================================================
 * BUAT APA : Kemampuan "exception" dan "delay" untuk model Shipment, dipisah ke
 *            trait (pola sama dengan HasTracking) supaya Shipment.php cukup
 *            ditambah SATU kata di baris `use`.
 * ISI      : relasi exceptions (hasMany), exception yang belum selesai, dan
 *            hasil perhitungan delay dari EtaService.
 * ============================================================
 */

namespace App\Models;

use App\Services\DelayAssessment;
use App\Services\EtaService;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasExceptions
{
    public function exceptions(): HasMany
    {
        return $this->hasMany(ShipmentException::class);
    }

    // OPEN + INVESTIGATING
    public function unresolvedExceptions(): HasMany
    {
        return $this->exceptions()->where('status', '!=', ShipmentException::STATUS_RESOLVED);
    }

    /**
     * Shipment yang SEDANG terlambat (belum selesai dan ETA melewati jadwal, atau jadwal
     * sudah lewat tanpa tiba). Sama persis dengan aturan EtaService::assess() untuk
     * shipment yang belum terminal, tetapi dihitung di database supaya bisa dipakai
     * untuk menghitung dan menyaring: Shipment::delayed()->count().
     */
    public function scopeDelayed($query)
    {
        return $query->whereNotIn('status', Shipment::TERMINAL_STATUSES)
            ->where(function ($q) {
                $q->whereColumn('estimated_arrival_at', '>', 'expected_arrival_at')
                  ->orWhere('expected_arrival_at', '<', now());
            });
    }

    // Hasil aturan delay untuk shipment ini (lihat EtaService)
    public function delay(): DelayAssessment
    {
        return app(EtaService::class)->assess($this);
    }
}
