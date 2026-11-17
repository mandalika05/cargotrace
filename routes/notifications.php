<?php
/*
 * ============================================================
 * BUAT APA : Route web P13.3: notifikasi in-app dan audit log.
 * DIMUAT   : lewat satu baris require di routes/web.php.
 * ============================================================
 */

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->whereUuid('id')->name('notifications.read');

    // Audit log: khusus admin
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('role:admin')->name('audit-logs.index');
});
