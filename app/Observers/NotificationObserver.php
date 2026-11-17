<?php
/*
 * ============================================================
 * BUAT APA : Membuat notifikasi saat TrackingEvent atau ShipmentException berubah.
 *            Didaftarkan di AppServiceProvider (service lama tidak diubah).
 * ============================================================
 */

namespace App\Observers;

use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Model;

class NotificationObserver
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function created(Model $model): void
    {
        if (! NotificationService::enabled()) {
            return;
        }

        if ($model instanceof TrackingEvent) {
            $this->notifications->trackingEventCreated($model);
        } elseif ($model instanceof ShipmentException) {
            $this->notifications->exceptionCreated($model);
        }
    }

    public function updated(Model $model): void
    {
        if (NotificationService::enabled() && $model instanceof ShipmentException) {
            $this->notifications->exceptionUpdated($model);
        }
    }
}
