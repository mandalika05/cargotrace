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
use Illuminate\Support\Facades\URL;
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
        // P15-fix4: di hosting (SnapDeploy) proxy tidak selalu mengirim header yang dikenali Laravel,
        // sehingga link CSS/JS/form dibuat http:// dan diblokir browser (Mixed Content).
        // Bila aplikasi produksi dan APP_URL berawalan https://, paksa semua URL dan request jadi HTTPS.
        if ($this->app->environment('production') && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
            $this->app['request']->server->set('HTTPS', 'on');
        }

        // P13.3: audit log untuk perubahan penting. Model-model ini dipantau AuditObserver.
        foreach ([Shipment::class, TrackingEvent::class, ShipmentException::class, User::class, Carrier::class, Location::class, Setting::class] as $model) {
            $model::observe(AuditObserver::class);
        }

        // P13.3: notifikasi in-app saat event tracking atau exception baru/berubah.
        TrackingEvent::observe(NotificationObserver::class);
        ShipmentException::observe(NotificationObserver::class);
    }
}
