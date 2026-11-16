<?php
/*
 * ============================================================
 * BUAT APA : Route P11 (exception, ETA, needs attention, settings), dipisah supaya
 *            routes/web.php yang sudah final hanya ditambah SATU baris require
 *            (pola yang sama dengan routes/tracking.php).
 * IZIN     : auth = sudah login | role:... = peran tertentu | Form Request / Policy
 *            memeriksa izin per data di server.
 * ============================================================
 */

use App\Http\Controllers\AttentionController;
use App\Http\Controllers\EtaController;
use App\Http\Controllers\ExceptionController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Semua peran: melihat exception (customer hanya miliknya, dibatasi di service)
    Route::get('/exceptions', [ExceptionController::class, 'index'])->name('exceptions.index');

    // Staf: catat exception, ubah status, perbarui ETA (izin dicek di Form Request)
    Route::post('/shipments/{shipment}/exceptions', [ExceptionController::class, 'store'])->name('shipments.exceptions.store');
    Route::patch('/exceptions/{exception}', [ExceptionController::class, 'update'])->name('exceptions.update');
    Route::patch('/shipments/{shipment}/eta', [EtaController::class, 'update'])->name('shipments.eta.update');

    // Antrean "Needs attention": khusus staf
    Route::get('/attention', AttentionController::class)->middleware('role:admin,operator')->name('attention.index');

    // Pengaturan ambang delay: khusus Admin
    Route::middleware('role:admin')->group(function () {
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });
});
