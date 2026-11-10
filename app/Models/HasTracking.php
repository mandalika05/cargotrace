<?php
/*
 * ============================================================
 * BUAT APA : Kemampuan "tracking" untuk model Shipment, dipisah ke trait
 *            supaya file Shipment.php yang sudah final cukup ditambah SATU baris
 *            (use HasFactory, SoftDeletes, HasTracking;).
 * ISI      : relasi trackingEvents (hasMany), lokasi terkini, dan daftar
 *            status berikutnya yang sah untuk shipment ini.
 * ============================================================
 */

namespace App\Models;

use App\Services\ShipmentStateMachine;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasTracking
{
    public function trackingEvents(): HasMany
    {
        return $this->hasMany(TrackingEvent::class);
    }

    // Lokasi terkini = event TERBARU yang punya informasi lokasi (nama atau koordinat)
    public function currentLocationEvent(): ?TrackingEvent
    {
        return $this->trackingEvents()
            ->where(function ($q) {
                $q->whereNotNull('location_name')->orWhereNotNull('latitude');
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();
    }

    // Status yang boleh dituju dari status sekarang (dari state machine)
    public function allowedNextStatuses(): array
    {
        return app(ShipmentStateMachine::class)->allowedNext((string) $this->status);
    }
}
