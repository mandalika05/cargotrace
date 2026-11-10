<?php
/*
 * ============================================================
 * BUAT APA : Daftar route halaman web.
 * ATURAN   : guest = hanya tamu, auth = sudah login,
 *            role:admin = hanya Admin (RoleMiddleware).
 * Route::resource membuat 7 route sekaligus (index, create, store, show,
 * edit, update, destroy) dengan nama shipments.index, shipments.show, dst.
 * Izin per-shipment dijaga ShipmentPolicy di controller.
 * ============================================================
 */

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CarrierController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// Hanya tamu (belum login)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});

// Semua peran yang sudah login
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Shipment: izin per aksi dicek ShipmentPolicy (customer hanya miliknya, dst)
    Route::resource('shipments', ShipmentController::class);

    // Data master & user: khusus Admin
    Route::middleware('role:admin')->group(function () {
        Route::resource('carriers', CarrierController::class)->except('show');
        Route::resource('locations', LocationController::class)->except('show');
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
    });
});
