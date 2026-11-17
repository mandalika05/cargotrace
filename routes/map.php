<?php
/*
 * ============================================================
 * BUAT APA : Route peta dan geocoding (P12). Dimuat dari routes/web.php lewat SATU baris require.
 * ============================================================
 */

use App\Http\Controllers\GeocodeController;
use App\Http\Controllers\MapController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Data peta satu shipment (customer hanya miliknya; dicek Policy)
    Route::get('/shipments/{shipment}/map', [MapController::class, 'show'])->name('shipments.map');

    // Peta dashboard (semua shipment berjalan sesuai peran)
    Route::get('/dashboard/map', [MapController::class, 'overview'])->name('dashboard.map');

    // Cari koordinat dari nama tempat: staf saja, dibatasi 20 per menit (aturan Nominatim)
    Route::get('/geocode', [GeocodeController::class, 'search'])
        ->middleware(['role:admin,operator', 'throttle:20,1'])
        ->name('geocode');
});
