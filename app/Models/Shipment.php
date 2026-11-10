<?php
/*
 * ============================================================
 * BUAT APA : Model shipment = pengiriman utama.
 * KEAMANAN : $fillable HANYA berisi data yang boleh diisi dari form.
 *            status, shipment_number, created_by, delivered_at, cancelled_at
 *            TIDAK ada di $fillable: hanya Service yang boleh mengisinya,
 *            jadi status tidak bisa diubah sembarangan lewat input.
 * RELASI   : belongsTo customer/carrier/origin/destination/creator,
 *            hasMany items.
 * SoftDeletes : dihapus = diberi tanda deleted_at, bukan hilang.
 * ============================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_CREATED = 'CREATED';
    public const STATUS_BOOKED = 'BOOKED';
    public const STATUS_PICKED_UP = 'PICKED_UP';
    public const STATUS_IN_TRANSIT = 'IN_TRANSIT';
    public const STATUS_DELIVERED = 'DELIVERED';
    public const STATUS_CANCELLED = 'CANCELLED';

    // Urutan ini juga urutan di dropdown filter
    public const STATUSES = [
        self::STATUS_CREATED,
        self::STATUS_BOOKED,
        self::STATUS_PICKED_UP,
        self::STATUS_IN_TRANSIT,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    // Status terminal: sudah selesai, tidak boleh diubah lagi
    public const TERMINAL_STATUSES = [self::STATUS_DELIVERED, self::STATUS_CANCELLED];

    protected $fillable = [
        'customer_id', 'carrier_id', 'origin_id', 'destination_id',
        'cargo_type', 'weight_kg', 'volume_cbm',
        'expected_departure_at', 'expected_arrival_at', 'estimated_arrival_at',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
            'volume_cbm' => 'decimal:2',
            'expected_departure_at' => 'datetime',
            'expected_arrival_at' => 'datetime',
            'estimated_arrival_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    // ---------- Relasi ----------
    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
    public function carrier(): BelongsTo  { return $this->belongsTo(Carrier::class); }
    public function origin(): BelongsTo   { return $this->belongsTo(Location::class, 'origin_id'); }
    public function destination(): BelongsTo { return $this->belongsTo(Location::class, 'destination_id'); }
    public function items(): HasMany      { return $this->hasMany(ShipmentItem::class); }

    // ---------- Pembantu status ----------
    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    // "IN_TRANSIT" -> "IN TRANSIT" untuk ditampilkan
    public function statusLabel(): string
    {
        return str_replace('_', ' ', (string) $this->status);
    }

    // Nama kelas CSS chip warna (didefinisikan di resources/css/app.css)
    public function statusChip(): string
    {
        return match ($this->status) {
            self::STATUS_PICKED_UP, self::STATUS_IN_TRANSIT => 'chip-transit',
            self::STATUS_DELIVERED => 'chip-delivered',
            self::STATUS_CANCELLED => 'chip-cancelled',
            default => 'chip-neutral',
        };
    }

    // Persentase bar progres di kartu shipment
    public function progressPercent(): int
    {
        return match ($this->status) {
            self::STATUS_CREATED => 10,
            self::STATUS_BOOKED => 30,
            self::STATUS_PICKED_UP => 50,
            self::STATUS_IN_TRANSIT => 75,
            self::STATUS_DELIVERED => 100,
            default => 0, // CANCELLED
        };
    }
}
