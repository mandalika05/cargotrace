<?php
/*
 * ============================================================
 * BUAT APA : Model satu kejadian dalam perjalanan shipment.
 * APPEND-ONLY: mengubah atau menghapus event melempar LogicException.
 *            Ini menjaga syarat brief: "tracking event bersifat historis".
 * created_by & shipment_id TIDAK ada di $fillable: diisi oleh TrackingService.
 * ============================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class TrackingEvent extends Model
{
    public const UPDATED_AT = null; // tabel hanya punya created_at

    public const TYPE_LOCATION_UPDATE = 'LOCATION_UPDATE';
    public const TYPE_REOPENED = 'REOPENED';

    protected $fillable = [
        'event_type', 'location_id', 'location_name',
        'latitude', 'longitude', 'occurred_at', 'description',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Tracking events are history and cannot be edited.');
        });

        static::deleting(function () {
            throw new LogicException('Tracking events are history and cannot be deleted.');
        });
    }

    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }

    // Urut kronologis: waktu kejadian, lalu urutan catat
    public function scopeChronological($query)
    {
        return $query->orderBy('occurred_at')->orderBy('id');
    }

    public function isStatusChange(): bool
    {
        return in_array($this->event_type, Shipment::STATUSES, true);
    }

    // Judul di timeline
    public function label(): string
    {
        return match ($this->event_type) {
            Shipment::STATUS_BOOKED => 'Booked',
            Shipment::STATUS_PICKED_UP => 'Picked up',
            Shipment::STATUS_IN_TRANSIT => 'In transit',
            Shipment::STATUS_DELIVERED => 'Delivered',
            Shipment::STATUS_CANCELLED => 'Cancelled',
            self::TYPE_LOCATION_UPDATE => 'Location update',
            self::TYPE_REOPENED => 'Reopened',
            default => ucfirst(strtolower(str_replace('_', ' ', (string) $this->event_type))),
        };
    }

    // Kelas warna titik timeline (didefinisikan di resources/css/tracking.css)
    public function dotClass(): string
    {
        return match ($this->event_type) {
            Shipment::STATUS_PICKED_UP, Shipment::STATUS_IN_TRANSIT, self::TYPE_LOCATION_UPDATE => 'tl-dot-transit',
            Shipment::STATUS_DELIVERED => 'tl-dot-delivered',
            Shipment::STATUS_CANCELLED => 'tl-dot-cancelled',
            self::TYPE_REOPENED => 'tl-dot-reopened',
            default => 'tl-dot-neutral',
        };
    }

    // "Suez Canal, Egypt" atau koordinat kalau tidak ada nama
    public function placeLabel(): ?string
    {
        if ($this->location_name) {
            return $this->location_name;
        }

        if ($this->latitude !== null && $this->longitude !== null) {
            return $this->latitude.', '.$this->longitude;
        }

        return null;
    }
}
