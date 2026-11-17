<?php
/*
 * ============================================================
 * BUAT APA : Mencatat AUDIT LOG: siapa (aktor), melakukan apa (aksi), kapan, pada data apa
 *            (entity), dan nilai sebelum/sesudahnya. Syarat brief: audit log untuk
 *            perubahan penting.
 * CARA KERJA: AuditObserver memanggil created/updated/deleted/restored setiap kali model
 *            yang dipantau disimpan. Service dan controller yang sudah jadi TIDAK diubah.
 * DIPANTAU : shipment, tracking_event (termasuk REOPENED beserta alasannya), exception,
 *            user, carrier, location, setting. Daftarnya ada di definition().
 * AMAN     : hanya kolom di daftar 'fields' yang dicatat. Password dan token TIDAK PERNAH
 *            ada di daftar itu. Pencatatan yang gagal tidak membatalkan aksi pengguna.
 * AKTOR    : user yang sedang login; kosong = sistem / belum login (mis. registrasi).
 * KONSOL   : artisan/seeder tidak dicatat (lihat enabled()); tes tetap mencatat.
 * ============================================================
 */

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class AuditService
{
    // Dicatat bila: tidak dimatikan di config, dan datang dari web/API (atau sedang uji coba)
    public static function enabled(): bool
    {
        if (! config('cargotrace.audit.enabled', true)) {
            return false;
        }

        return ! app()->runningInConsole() || app()->runningUnitTests();
    }

    public function created(Model $model): void
    {
        $this->safely(function () use ($model) {
            if ($d = $this->definition($model)) {
                $this->write($d['entity'].'.created', $model, $d['label'], null, $this->snapshot($model, $d['fields']));
            }
        });
    }

    public function updated(Model $model): void
    {
        $this->safely(function () use ($model) {
            $d = $this->definition($model);
            if (! $d) {
                return;
            }

            $old = [];
            $new = [];
            foreach ($d['fields'] as $field) {
                if ($model->wasChanged($field)) {
                    $old[$field] = $this->normalize($model->getRawOriginal($field));
                    $new[$field] = $this->normalize($model->getAttributes()[$field] ?? null);
                }
            }

            // Perubahan yang bukan kolom pantauan (mis. updated_at saja) tidak dicatat
            if ($new !== []) {
                $this->write($d['entity'].'.updated', $model, $d['label'], $old, $new);
            }
        });
    }

    public function deleted(Model $model): void
    {
        $this->safely(function () use ($model) {
            if ($d = $this->definition($model)) {
                $this->write($d['entity'].'.deleted', $model, $d['label'], $this->snapshot($model, $d['fields']), null);
            }
        });
    }

    public function restored(Model $model): void
    {
        $this->safely(function () use ($model) {
            if ($d = $this->definition($model)) {
                $this->write($d['entity'].'.restored', $model, $d['label'], null, null);
            }
        });
    }

    /**
     * Apa yang dipantau untuk tiap jenis model: nama entity, label yang mudah dibaca,
     * dan daftar kolom yang boleh dicatat.
     *
     * @return array{entity: string, label: string, fields: array<int, string>}|null
     */
    private function definition(Model $model): ?array
    {
        return match (true) {
            $model instanceof Shipment => [
                'entity' => 'shipment',
                'label' => (string) $model->shipment_number,
                'fields' => [
                    'status', 'customer_id', 'carrier_id', 'origin_id', 'destination_id', 'cargo_type',
                    'weight_kg', 'volume_cbm', 'expected_departure_at', 'expected_arrival_at',
                    'estimated_arrival_at', 'delivered_at', 'cancelled_at',
                ],
            ],
            $model instanceof TrackingEvent => [
                'entity' => 'tracking_event',
                'label' => (Shipment::withTrashed()->whereKey($model->shipment_id)->value('shipment_number') ?? '#'.$model->shipment_id).' / '.$model->event_type,
                'fields' => ['shipment_id', 'event_type', 'location_name', 'description', 'occurred_at'],
            ],
            $model instanceof ShipmentException => [
                'entity' => 'exception',
                'label' => 'Exception #'.$model->getKey(),
                'fields' => ['shipment_id', 'type', 'severity', 'status', 'description', 'resolution_note'],
            ],
            $model instanceof User => [
                'entity' => 'user',
                'label' => (string) $model->email,
                'fields' => ['name', 'email', 'role'], // password TIDAK pernah dicatat
            ],
            $model instanceof Carrier => [
                'entity' => 'carrier',
                'label' => (string) $model->name,
                'fields' => ['name', 'code', 'contact_email', 'is_active'],
            ],
            $model instanceof Location => [
                'entity' => 'location',
                'label' => (string) $model->name,
                'fields' => ['name', 'city', 'country', 'latitude', 'longitude'],
            ],
            $model instanceof Setting => [
                'entity' => 'setting',
                'label' => (string) $model->key,
                'fields' => ['key', 'value'],
            ],
            default => null,
        };
    }

    /**
     * @param array<int, string> $fields
     * @return array<string, mixed>
     */
    private function snapshot(Model $model, array $fields): array
    {
        $out = [];
        foreach ($fields as $field) {
            $out[$field] = $this->normalize($model->getAttributes()[$field] ?? null);
        }

        return $out;
    }

    // Nilai dibuat sederhana (teks/angka/bool/null) supaya JSON-nya rapi dan konsisten
    private function normalize(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_scalar($value) || $value === null ? $value : json_encode($value);
    }

    /**
     * @param array<string, mixed>|null $old
     * @param array<string, mixed>|null $new
     */
    private function write(string $action, Model $model, string $label, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => Str::before($action, '.'),
            'entity_id' => $model->getKey(),
            'entity_label' => Str::limit($label, 150, ''),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => request()->ip(),
        ]);
    }

    // Kegagalan mencatat audit TIDAK boleh menggagalkan aksi pengguna: dilaporkan ke log saja
    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
