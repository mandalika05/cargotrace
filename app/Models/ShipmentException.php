<?php
/*
 * ============================================================
 * BUAT APA : Model exception = gangguan pada sebuah shipment (slide 8 brief).
 * JENIS    : CUSTOMS_HOLD, WEATHER_DELAY, PORT_CONGESTION, DAMAGED, MISROUTED,
 *            DOCUMENTATION_ISSUE.
 * SEVERITY : LOW / MEDIUM / HIGH.
 * STATUS   : OPEN -> INVESTIGATING -> RESOLVED (RESOLVED = selesai, tidak kembali).
 *            Daftar perpindahan yang sah ada di TRANSITIONS di bawah (satu-satunya tempat).
 * KEAMANAN : $fillable hanya type, severity, description. status, detected_at,
 *            resolved_*, reported_by diisi ExceptionService, bukan dari input bebas.
 * ============================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentException extends Model
{
    use HasFactory;

    public const TYPE_CUSTOMS_HOLD = 'CUSTOMS_HOLD';
    public const TYPE_WEATHER_DELAY = 'WEATHER_DELAY';
    public const TYPE_PORT_CONGESTION = 'PORT_CONGESTION';
    public const TYPE_DAMAGED = 'DAMAGED';
    public const TYPE_MISROUTED = 'MISROUTED';
    public const TYPE_DOCUMENTATION_ISSUE = 'DOCUMENTATION_ISSUE';

    public const TYPES = [
        self::TYPE_CUSTOMS_HOLD,
        self::TYPE_WEATHER_DELAY,
        self::TYPE_PORT_CONGESTION,
        self::TYPE_DAMAGED,
        self::TYPE_MISROUTED,
        self::TYPE_DOCUMENTATION_ISSUE,
    ];

    public const SEVERITY_LOW = 'LOW';
    public const SEVERITY_MEDIUM = 'MEDIUM';
    public const SEVERITY_HIGH = 'HIGH';

    // Urut dari paling ringan ke paling gawat
    public const SEVERITIES = [self::SEVERITY_LOW, self::SEVERITY_MEDIUM, self::SEVERITY_HIGH];

    public const STATUS_OPEN = 'OPEN';
    public const STATUS_INVESTIGATING = 'INVESTIGATING';
    public const STATUS_RESOLVED = 'RESOLVED';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_INVESTIGATING, self::STATUS_RESOLVED];

    // Peta jalan status exception. RESOLVED = akhir (tidak ada jalan keluar).
    public const TRANSITIONS = [
        self::STATUS_OPEN => [self::STATUS_INVESTIGATING, self::STATUS_RESOLVED],
        self::STATUS_INVESTIGATING => [self::STATUS_RESOLVED],
        self::STATUS_RESOLVED => [],
    ];

    protected $fillable = ['type', 'severity', 'description'];

    // Mengubah kolom waktu dari teks database menjadi objek tanggal (Carbon) supaya mudah diformat dan dibandingkan.
    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    // ---------- Relasi ----------
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    // Relasi: staf yang melaporkan exception ini (kolom reported_by -> users).
    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reported_by'); }
    // Relasi: staf yang menyelesaikan exception ini (kolom resolved_by -> users).
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }

    // ---------- Scope ----------
    // Belum selesai = OPEN atau INVESTIGATING
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_RESOLVED);
    }

    // ---------- Pembantu ----------
    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    // Daftar status tujuan yang sah dari status sekarang, dibaca dari peta TRANSITIONS di atas.
    // Contoh: OPEN -> [INVESTIGATING, RESOLVED]; RESOLVED -> [] (sudah selesai).
    public function allowedNextStatuses(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }

    // "PORT_CONGESTION" -> "Port congestion"
    public static function typeLabel(string $type): string
    {
        return ucfirst(strtolower(str_replace('_', ' ', $type)));
    }

    // Nama jenis exception yang enak dibaca untuk exception ini (memakai typeLabel di atas).
    public function label(): string
    {
        return self::typeLabel((string) $this->type);
    }

    // Nama status yang enak dibaca: "INVESTIGATING" -> "Investigating".
    public function statusLabel(): string
    {
        return ucfirst(strtolower((string) $this->status));
    }

    // Kelas chip warna severity (didefinisikan di polish.css)
    public function severityChip(): string
    {
        return match ($this->severity) {
            self::SEVERITY_HIGH => 'chip-exception',
            self::SEVERITY_MEDIUM => 'chip-delay',
            default => 'chip-neutral',
        };
    }

    // Kelas chip warna untuk STATUS exception: selesai hijau, diselidiki biru, terbuka merah.
    public function statusChip(): string
    {
        return match ($this->status) {
            self::STATUS_RESOLVED => 'chip-delivered',
            self::STATUS_INVESTIGATING => 'chip-transit',
            default => 'chip-exception',
        };
    }
}
