<?php
/*
 * ============================================================
 * BUAT APA : Route khusus tracking, dipisah supaya routes/web.php yang
 *            sudah final tidak perlu ditimpa (web.php hanya menambah satu
 *            baris require di bagian paling bawah).
 * ============================================================
 */

use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Tambah event: izin dicek TrackingEventRequest (staf, shipment belum terminal)
    Route::post('/shipments/{shipment}/tracking', [TrackingController::class, 'store'])
        ->name('shipments.tracking.store');

    // Prosedur khusus Admin: buka kembali shipment terminal
    Route::post('/shipments/{shipment}/reopen', [TrackingController::class, 'reopen'])
        ->middleware('role:admin')
        ->name('shipments.reopen');
});
