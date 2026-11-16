<?php
/*
 * ============================================================
 * BUAT APA : REST API versi 1 (syarat brief: REST API + token Sanctum). Alamat dasar: /api/v1
 *            Dimuat dari routes/api.php lewat SATU baris require (pola sama dengan web).
 * KEAMANAN : - Login memakai token Bearer (Laravel Sanctum).
 *            - Hak akses SAMA PERSIS dengan web: dijaga Policy / Form Request / Service
 *              yang sama, bukan hanya disembunyikan. Customer hanya melihat miliknya.
 *            - Login dan register dibatasi 10 percobaan per menit; API lain 60 per menit.
 * ============================================================
 */

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DelayController;
use App\Http\Controllers\Api\V1\ExceptionController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\ShipmentController;
use App\Http\Controllers\Api\V1\TrackingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->group(function () {
    // ----- Tanpa token -----
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('auth.login');
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('auth.register');

    // ----- Dengan token -----
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('/dashboard', [LookupController::class, 'dashboard'])->name('dashboard');

        // Shipment (izin per peran dicek Policy di dalam controller / Form Request)
        Route::apiResource('shipments', ShipmentController::class);

        // Tracking
        Route::get('/shipments/{shipment}/tracking', [TrackingController::class, 'index'])->name('shipments.tracking.index');
        Route::post('/shipments/{shipment}/tracking', [TrackingController::class, 'store'])->name('shipments.tracking.store');
        Route::post('/shipments/{shipment}/reopen', [TrackingController::class, 'reopen'])->middleware('role:admin')->name('shipments.reopen');

        // Prakiraan cuaca (posisi terkini + tujuan)
        Route::get('/shipments/{shipment}/weather', [\App\Http\Controllers\WeatherController::class, 'show'])->name('shipments.weather');

        // Delay dan ETA
        Route::get('/shipments/{shipment}/delay', [DelayController::class, 'show'])->name('shipments.delay');
        Route::patch('/shipments/{shipment}/eta', [DelayController::class, 'updateEta'])->name('shipments.eta');

        // Exception
        Route::get('/exceptions', [ExceptionController::class, 'index'])->name('exceptions.index');
        Route::patch('/exceptions/{exception}', [ExceptionController::class, 'update'])->name('exceptions.update');
        Route::get('/shipments/{shipment}/exceptions', [ExceptionController::class, 'forShipment'])->name('shipments.exceptions.index');
        Route::post('/shipments/{shipment}/exceptions', [ExceptionController::class, 'store'])->name('shipments.exceptions.store');

        // Khusus staf
        Route::middleware('role:admin,operator')->group(function () {
            Route::get('/attention', [DelayController::class, 'attention'])->name('attention');
            Route::get('/carriers', [LookupController::class, 'carriers'])->name('carriers');
            Route::get('/locations', [LookupController::class, 'locations'])->name('locations');
            Route::get('/settings/delay', [LookupController::class, 'delaySettings'])->name('settings.delay');
        });

        // Khusus Admin
        Route::put('/settings/delay', [LookupController::class, 'updateDelaySettings'])->middleware('role:admin')->name('settings.delay.update');
    });
});
