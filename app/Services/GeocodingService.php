<?php
/*
 * ============================================================
 * BUAT APA : GEOCODING = mengubah nama tempat ("Port of Rotterdam") jadi koordinat
 *            (latitude/longitude). API eksternal kedua (syarat brief: minimal satu,
 *            dua disarankan). Sumber: Nominatim OpenStreetMap, gratis tanpa API key.
 * KEGUNAAN : staf tidak perlu mencari koordinat manual saat mengisi lokasi atau event.
 * ATURAN   : Nominatim minta User-Agent jelas dan paling banyak 1 permintaan/detik.
 *            Karena itu hasil di-cache 24 jam dan tombol di web dibatasi per menit.
 * KEGAGALAN: timeout / error / data kosong ditangani: hasilnya 'available' => false atau
 *            daftar kosong, tidak pernah melempar error ke pengguna.
 * ============================================================
 */

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeocodingService
{
    /**
     * @return array{available: bool, results: array<int, array{label: string, latitude: float, longitude: float}>}
     */
    public function search(string $query): array
    {
        $query = trim(preg_replace('/\s+/', ' ', $query) ?? '');
        if (mb_strlen($query) < 3) {
            return ['available' => true, 'results' => []];
        }

        $key = 'cargotrace.geocode.'.sha1(mb_strtolower($query));
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout((int) config('cargotrace.geocoding.timeout'))
                ->withHeaders(['User-Agent' => config('cargotrace.geocoding.user_agent')])
                ->acceptJson()
                ->get(config('cargotrace.geocoding.url'), [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => (int) config('cargotrace.geocoding.limit'),
                ]);

            if (! $response->successful() || ! is_array($response->json())) {
                return ['available' => false, 'results' => []];
            }

            $results = [];
            foreach ($response->json() as $row) {
                if (! isset($row['lat'], $row['lon'])) {
                    continue; // baris rusak dilewati
                }
                $results[] = [
                    'label' => (string) ($row['display_name'] ?? $query),
                    'latitude' => round((float) $row['lat'], 7),
                    'longitude' => round((float) $row['lon'], 7),
                ];
            }
        } catch (Throwable) {
            return ['available' => false, 'results' => []]; // offline / timeout
        }

        $out = ['available' => true, 'results' => $results];
        Cache::put($key, $out, now()->addMinutes((int) config('cargotrace.geocoding.cache_minutes')));

        return $out;
    }
}
