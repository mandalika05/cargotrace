<?php
/*
 * ============================================================
 * BUAT APA : Membuat notifikasi in-app (syarat brief: in-app notification minimal).
 *            Dipanggil oleh NotificationObserver, jadi service lama tidak diubah.
 * SIAPA MENERIMA APA:
 *   Customer pemilik shipment : status berubah (booked, picked up, in transit, delivered,
 *                               cancelled, reopened), ETA diubah, exception dilaporkan dan
 *                               exception selesai.
 *   Admin dan Operator        : exception baru dilaporkan (kecuali pelakunya sendiri).
 *   Update lokasi biasa TIDAK membuat notifikasi (terlalu sering).
 * AMAN     : kegagalan membuat notifikasi tidak membatalkan aksi pengguna.
 * ============================================================
 */

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Notifications\ShipmentNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Throwable;

class NotificationService
{
    // Notifikasi dibuat bila datang dari web/API (atau tes), sama seperti audit log
    public static function enabled(): bool
    {
        return AuditService::enabled();
    }

    // Event tracking baru: perubahan status atau ETA diubah
    public function trackingEventCreated(TrackingEvent $event): void
    {
        $this->safely(function () use ($event) {
            $shipment = Shipment::withTrashed()->with('customer')->find($event->shipment_id);
            if (! $shipment) {
                return;
            }

            // Penanda exception ditangani lewat exceptionCreated/exceptionUpdated; lokasi biasa diabaikan
            if (in_array($event->event_type, [
                TrackingEvent::TYPE_EXCEPTION,
                TrackingEvent::TYPE_EXCEPTION_UPDATE,
                TrackingEvent::TYPE_EXCEPTION_RESOLVED,
                TrackingEvent::TYPE_LOCATION_UPDATE,
            ], true)) {
                return;
            }

            if ($event->event_type === TrackingEvent::TYPE_ETA_UPDATED) {
                $this->toOwner($shipment, 'eta_updated', 'warning', 'ETA updated', (string) ($event->description ?: 'The estimated arrival time was changed.'));

                return;
            }

            // Selain itu: perubahan status (nilai event_type sama dengan status barunya)
            $level = match ($event->event_type) {
                'DELIVERED' => 'success',
                'CANCELLED' => 'danger',
                TrackingEvent::TYPE_REOPENED => 'warning',
                default => 'info',
            };
            $place = $event->placeLabel();

            $this->toOwner(
                $shipment,
                'status_changed',
                $level,
                $event->label(),
                $event->label().($place ? ' - '.$place : ''),
            );
        });
    }

    // Exception baru: staf (kecuali pelaku) dan customer pemilik
    public function exceptionCreated(ShipmentException $exception): void
    {
        $this->safely(function () use ($exception) {
            $shipment = Shipment::withTrashed()->with('customer')->find($exception->shipment_id);
            if (! $shipment) {
                return;
            }

            $level = match ($exception->severity) {
                ShipmentException::SEVERITY_HIGH => 'danger',
                ShipmentException::SEVERITY_MEDIUM => 'warning',
                default => 'info',
            };
            $title = 'Exception reported';
            $message = ShipmentException::typeLabel($exception->type).' ('.ucfirst(strtolower($exception->severity)).' severity)';

            $this->toStaff($shipment, 'exception_reported', $level, $title, $message);
            $this->toOwner($shipment, 'exception_reported', $level, $title, $message);
        });
    }

    // Exception selesai: customer pemilik diberi tahu
    public function exceptionUpdated(ShipmentException $exception): void
    {
        $this->safely(function () use ($exception) {
            if (! $exception->wasChanged('status') || $exception->status !== ShipmentException::STATUS_RESOLVED) {
                return;
            }

            $shipment = Shipment::withTrashed()->with('customer')->find($exception->shipment_id);
            if ($shipment) {
                $this->toOwner($shipment, 'exception_resolved', 'success', 'Exception resolved', ShipmentException::typeLabel($exception->type).' has been resolved.');
            }
        });
    }

    private function toOwner(Shipment $shipment, string $kind, string $level, string $title, string $message): void
    {
        $customer = $shipment->customer;
        if ($customer && $customer->id !== Auth::id()) {
            $customer->notify($this->make($shipment, $kind, $level, $title, $message));
        }
    }

    private function toStaff(Shipment $shipment, string $kind, string $level, string $title, string $message): void
    {
        $staff = User::query()
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_OPERATOR])
            ->when(Auth::id(), fn ($q, $id) => $q->where('id', '!=', $id))
            ->get();

        if ($staff->isNotEmpty()) {
            Notification::send($staff, $this->make($shipment, $kind, $level, $title, $message));
        }
    }

    private function make(Shipment $shipment, string $kind, string $level, string $title, string $message): ShipmentNotification
    {
        return new ShipmentNotification($kind, $level, $shipment->shipment_number.': '.$title, $message, $shipment->id, $shipment->shipment_number);
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
