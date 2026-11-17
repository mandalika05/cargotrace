<?php
/*
 * ============================================================
 * BUAT APA : Satu baris jejak audit (siapa, melakukan apa, kapan, pada data apa).
 * APPEND-ONLY: log tidak boleh diubah atau dihapus lewat aplikasi (updating/deleting
 *            melempar error), sama seperti tracking event. Hanya ada created_at.
 * ISI      : dibuat otomatis oleh AuditService (lewat AuditObserver), bukan dari form.
 * ============================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditLog extends Model
{
    public const UPDATED_AT = null; // tabel hanya punya created_at

    // Jenis data yang dicatat (dipakai juga untuk dropdown filter)
    public const ENTITIES = ['shipment', 'tracking_event', 'exception', 'user', 'carrier', 'location', 'setting'];
    public const ACTION_TYPES = ['created', 'updated', 'deleted', 'restored'];

    protected $fillable = [
        'user_id', 'action', 'entity_type', 'entity_id', 'entity_label',
        'old_values', 'new_values', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Audit logs are history and cannot be edited.');
        });

        static::deleting(function () {
            throw new LogicException('Audit logs are history and cannot be deleted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Filter: entity, action_type, user_id, date_from, date_to, q (label).
     * Dipakai oleh halaman web dan REST API. Semua nilai dibatasi daftar putih / di-escape.
     *
     * @param array<string, mixed> $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (filled($filters['entity'] ?? null) && in_array($filters['entity'], self::ENTITIES, true)) {
            $query->where('entity_type', $filters['entity']);
        }

        if (filled($filters['action_type'] ?? null) && in_array($filters['action_type'], self::ACTION_TYPES, true)) {
            $query->where('action', 'like', '%.'.$filters['action_type']);
        }

        if (filled($filters['user_id'] ?? null) && (int) $filters['user_id'] > 0) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->where('created_at', '>=', $filters['date_from'].' 00:00:00');
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->where('created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        if (filled($filters['q'] ?? null)) {
            $query->where('entity_label', 'like', '%'.addcslashes($filters['q'], '%_\\').'%');
        }

        return $query;
    }

    // "shipment.created" -> "Created"
    public function actionLabel(): string
    {
        return ucfirst(substr(strrchr('.'.$this->action, '.'), 1));
    }

    // "tracking_event" -> "Tracking event"
    public function entityLabelText(): string
    {
        return ucfirst(str_replace('_', ' ', $this->entity_type));
    }

    // Warna lencana aksi (memakai kelas chip yang sudah ada)
    public function actionChip(): string
    {
        return match ($this->actionLabel()) {
            'Created' => 'chip-delivered',
            'Deleted' => 'chip-exception',
            'Restored' => 'chip-transit',
            default => 'chip-neutral',
        };
    }

    /**
     * Ringkasan perubahan untuk tampilan: ["status: BOOKED -> IN_TRANSIT", ...].
     * Nilai panjang dipotong supaya tabel tetap rapi.
     *
     * @return array<int, string>
     */
    public function changeLines(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));

        $lines = [];
        foreach ($keys as $key) {
            $has = fn ($arr) => array_key_exists($key, $arr);
            $fmt = fn ($v) => $v === null || $v === '' ? '-' : mb_strimwidth((string) (is_scalar($v) ? $v : json_encode($v)), 0, 60, '...');

            if ($has($old) && $has($new)) {
                $lines[] = $key.': '.$fmt($old[$key]).' -> '.$fmt($new[$key]);
            } elseif ($has($new)) {
                $lines[] = $key.': '.$fmt($new[$key]);
            } else {
                $lines[] = $key.': '.$fmt($old[$key]).' (removed)';
            }
        }

        return $lines;
    }
}
