<?php
/*
 * ============================================================
 * BUAT APA : Penjaga pintu berdasarkan peran (role).
 *            Dipasang di route, contoh: ->middleware('role:admin,operator')
 *            Kalau peran user tidak cocok, request ditolak (403).
 * POLA DARI: UTS MedikaCare (RoleMiddleware) - ditulis ulang untuk CargoTrace.
 * PERAN    : admin, operator, customer.
 * KENAPA DI SERVER: brief dosen: "jangan hanya menyembunyikan tombol di frontend".
 * Dipakai oleh: semua route web dan API (P8 dst).
 * ============================================================
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    // $roles berisi daftar peran yang boleh masuk, mis. ['admin', 'operator']
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $wantsJson = $request->is('api/*') || $request->expectsJson();

        // 1) Belum login -> 401 (API) atau diarahkan ke halaman login (web)
        if (! $user) {
            return $wantsJson
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->route('login');
        }

        // 2) Sudah login tapi perannya tidak diizinkan -> 403
        if (! in_array($user->role, $roles, true)) {
            return $wantsJson
                ? response()->json(['message' => 'Forbidden.'], 403)
                : abort(403, 'Kamu tidak punya akses ke halaman ini.');
        }

        // 3) Lolos -> lanjut ke controller
        return $next($request);
    }
}
