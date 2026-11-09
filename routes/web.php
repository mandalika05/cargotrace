<?php
/*
 * ============================================================
 * BUAT APA : Daftar route halaman web.
 * ATURAN   : guest = hanya untuk yang BELUM login, auth = hanya yang SUDAH login,
 *            role:xxx = hanya peran tertentu (lihat RoleMiddleware).
 * Route per peran ditambah bertahap (P9 dst). Contoh polanya:
 *   Route::middleware(['auth','role:admin'])->prefix('admin')->group(...)
 * ============================================================
 */

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// Hanya tamu (belum login)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    // throttle: maksimal 10 percobaan per menit (anti tebak password)
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});

// Semua peran yang sudah login
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});
