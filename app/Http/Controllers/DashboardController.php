<?php
/*
 * ============================================================
 * BUAT APA : Halaman utama setelah login.
 *            Sementara masih kerangka; isi dashboard asli (kartu angka,
 *            grafik, antrean "Needs attention") dibuat di P13.
 * ============================================================
 */

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard');
    }
}
