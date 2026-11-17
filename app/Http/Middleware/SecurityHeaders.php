<?php
/*
 * ============================================================
 * BUAT APA : Menambahkan header keamanan HTTP ke SETIAP respons (web dan API). P14.
 * HEADER   :
 *   X-Content-Type-Options: nosniff     -> browser tidak menebak tipe berkas (cegah salah tafsir skrip)
 *   X-Frame-Options: SAMEORIGIN         -> halaman tidak bisa dipasang di iframe situs lain (clickjacking)
 *   Referrer-Policy                     -> alamat halaman tidak dibocorkan penuh ke situs lain
 *   Permissions-Policy                  -> kamera/mikrofon/lokasi dimatikan (aplikasi tidak memakainya)
 *   Strict-Transport-Security (HSTS)    -> hanya dikirim bila koneksi HTTPS (di server produksi)
 * CATATAN  : Content-Security-Policy sengaja belum dipasang ketat karena layout memakai skrip
 *            inline kecil dan peta memuat tile dari OpenStreetMap. Lihat docs/SECURITY.md.
 * DIDAFTARKAN : bootstrap/app.php ($middleware->append).
 * ============================================================
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
