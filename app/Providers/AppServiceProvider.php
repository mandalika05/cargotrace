<?php

namespace App\Providers;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Observers\NotificationObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // P13.3: audit log untuk perubahan penting. Model-model ini dipantau AuditObserver.
        foreach ([Shipment::class, TrackingEvent::class, ShipmentException::class, User::class, Carrier::class, Location::class, Setting::class] as $model) {
            $model::observe(AuditObserver::class);
        }

        // P13.3: notifikasi in-app saat event tracking atau exception baru/berubah.
        TrackingEvent::observe(NotificationObserver::class);
        ShipmentException::observe(NotificationObserver::class);
    }
}
