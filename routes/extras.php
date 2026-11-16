<?php
/*
 * ============================================================
 * BUAT APA : Route fitur tambahan: lacak publik, prakiraan cuaca, export CSV.
 *            Dimuat dari routes/web.php lewat SATU baris require.
 * CATATAN  : export memakai /exports/shipments.csv (bukan /shipments/export), supaya tidak
 *            bentrok dengan route resource /shipments/{shipment}.
 * ============================================================
 */

use App\Http\Controllers\ExportController;
use App\Http\Controllers\PublicTrackingController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

// Tanpa login (dibatasi 20 pencarian per menit)
Route::get('/track', PublicTrackingController::class)->middleware('throttle:20,1')->name('track');

Route::middleware('auth')->group(function () {
    Route::get('/shipments/{shipment}/weather', [WeatherController::class, 'show'])->name('shipments.weather');
    Route::get('/exports/shipments.csv', [ExportController::class, 'shipments'])->name('exports.shipments');
});
