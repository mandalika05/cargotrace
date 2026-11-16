<?php
/*
 * ============================================================
 * BUAT APA : Prakiraan cuaca untuk shipment (API eksternal, syarat brief: geocoding/cuaca).
 *            Sumber: Open-Meteo (gratis, tanpa API key). Hasil di-cache 30 menit.
 *
 * forecast()    prakiraan harian untuk satu koordinat (aman: gagal -> available=false)
 * forShipment() dua titik: posisi TERKINI kapal/kargo dan TUJUAN, plus saran
 * RISIKO     : HIGH bila badai petir, angin >= 60 km/j, atau hujan >= 40 mm/hari;
 *              MEDIUM bila hujan lebat, angin >= 40 km/j, atau hujan >= 15 mm/hari.
 * SARAN      : bila ada risiko HIGH dalam 3 hari ke depan dan belum ada exception
 *              WEATHER_DELAY yang aktif, staf disarankan mencatatnya (tidak otomatis).
 * Layanan luar mati / offline: hasilnya available=false dan tampilan menampilkan pesan.
 * ============================================================
 */

namespace App\Services;

use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class WeatherService
{
    /** Kode cuaca WMO -> [teks, ikon] */
    private const CODES = [
        0 => ['Clear sky', '☀️'], 1 => ['Mostly clear', '🌤️'], 2 => ['Partly cloudy', '⛅'], 3 => ['Overcast', '☁️'],
        45 => ['Fog', '🌫️'], 48 => ['Fog', '🌫️'],
        51 => ['Light drizzle', '🌦️'], 53 => ['Drizzle', '🌦️'], 55 => ['Heavy drizzle', '🌧️'],
        56 => ['Freezing drizzle', '🌧️'], 57 => ['Freezing drizzle', '🌧️'],
        61 => ['Light rain', '🌦️'], 63 => ['Rain', '🌧️'], 65 => ['Heavy rain', '🌧️'],
        66 => ['Freezing rain', '🌧️'], 67 => ['Freezing rain', '🌧️'],
        71 => ['Light snow', '🌨️'], 73 => ['Snow', '🌨️'], 75 => ['Heavy snow', '❄️'], 77 => ['Snow grains', '🌨️'],
        80 => ['Rain showers', '🌦️'], 81 => ['Rain showers', '🌧️'], 82 => ['Violent showers', '🌧️'],
        85 => ['Snow showers', '🌨️'], 86 => ['Heavy snow showers', '❄️'],
        95 => ['Thunderstorm', '⛈️'], 96 => ['Thunderstorm with hail', '⛈️'], 99 => ['Severe thunderstorm', '⛈️'],
    ];

    private const HEAVY_CODES = [65, 67, 75, 82, 86];

    /**
     * @return array{available: bool, days: array<int, array<string, mixed>>}
     */
    public function forecast(float $latitude, float $longitude): array
    {
        $lat = round($latitude, 2);
        $lng = round($longitude, 2);
        $key = "cargotrace.weather.{$lat}.{$lng}";

        // Hanya hasil yang BERHASIL yang disimpan; kegagalan dicoba lagi di permintaan berikutnya
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout((int) config('cargotrace.weather.timeout'))
                ->acceptJson()
                ->get(config('cargotrace.weather.url'), [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,wind_speed_10m_max',
                    'timezone' => 'auto',
                    'forecast_days' => (int) config('cargotrace.weather.days'),
                ]);

            if (! $response->successful()) {
                return $this->unavailable();
            }

            $result = $this->parse((array) $response->json('daily'));
        } catch (Throwable) {
            return $this->unavailable(); // offline / timeout / DNS
        }

        if (! $result['available']) {
            return $result;
        }

        Cache::put($key, $result, now()->addMinutes((int) config('cargotrace.weather.cache_minutes')));

        return $result;
    }

    /**
     * Prakiraan untuk shipment yang masih berjalan: posisi terkini + tujuan.
     *
     * @return array<string, mixed>
     */
    public function forShipment(Shipment $shipment): array
    {
        if ($shipment->isTerminal()) {
            return ['applicable' => false, 'message' => 'Weather is shown only while a shipment is on its way.'];
        }

        $points = [];

        $current = $this->currentPoint($shipment);
        if ($current) {
            $points[] = $current;
        }

        $destination = $shipment->destination;
        if ($destination && $destination->latitude !== null && $destination->longitude !== null) {
            $same = $current && abs($current['latitude'] - (float) $destination->latitude) < 0.05
                && abs($current['longitude'] - (float) $destination->longitude) < 0.05;
            if (! $same) {
                $points[] = [
                    'kind' => 'destination',
                    'label' => trim(($destination->city ?: $destination->name).', '.$destination->country, ', '),
                    'latitude' => (float) $destination->latitude,
                    'longitude' => (float) $destination->longitude,
                ];
            }
        }

        if ($points === []) {
            return ['applicable' => true, 'available' => false, 'points' => [], 'message' => 'No coordinates are known for this shipment yet.', 'suggest_exception' => false];
        }

        $highSoon = false;
        $anyAvailable = false;

        foreach ($points as &$point) {
            $forecast = $this->forecast($point['latitude'], $point['longitude']);
            $point['available'] = $forecast['available'];
            $point['days'] = $forecast['days'];
            $anyAvailable = $anyAvailable || $forecast['available'];

            foreach (array_slice($forecast['days'], 0, 3) as $day) {
                if ($day['risk'] === ShipmentException::SEVERITY_HIGH) {
                    $highSoon = true;
                }
            }
        }
        unset($point);

        $hasWeatherException = $shipment->unresolvedExceptions()
            ->where('type', ShipmentException::TYPE_WEATHER_DELAY)->exists();

        return [
            'applicable' => true,
            'available' => $anyAvailable,
            'points' => $points,
            'message' => $anyAvailable ? null : 'Weather needs an internet connection and is not reachable right now.',
            'suggest_exception' => $highSoon && ! $hasWeatherException,
            'advice' => $highSoon
                ? 'Severe weather is expected within 3 days on this route. Consider reporting a Weather delay exception and updating the ETA.'
                : null,
        ];
    }

    // ---------- Pembantu ----------

    /** Posisi terkini dari event tracking terbaru yang punya koordinat (langsung atau lewat lokasi). */
    private function currentPoint(Shipment $shipment): ?array
    {
        $event = $shipment->trackingEvents()
            ->where(fn ($q) => $q->whereNotNull('latitude')->orWhereNotNull('location_id'))
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->first();

        if (! $event) {
            return null;
        }

        $lat = $event->latitude;
        $lng = $event->longitude;

        if (($lat === null || $lng === null) && $event->location_id) {
            $location = Location::find($event->location_id);
            $lat = $location?->latitude;
            $lng = $location?->longitude;
        }

        if ($lat === null || $lng === null) {
            return null;
        }

        return [
            'kind' => 'current',
            'label' => $event->placeLabel() ?: 'Current position',
            'latitude' => (float) $lat,
            'longitude' => (float) $lng,
        ];
    }

    /**
     * @param  array<string, mixed>  $daily
     * @return array{available: bool, days: array<int, array<string, mixed>>}
     */
    private function parse(array $daily): array
    {
        $dates = $daily['time'] ?? [];
        if (! is_array($dates) || $dates === []) {
            return $this->unavailable();
        }

        $days = [];
        foreach ($dates as $i => $date) {
            $code = (int) ($daily['weather_code'][$i] ?? 0);
            $rain = round((float) ($daily['precipitation_sum'][$i] ?? 0), 1);
            $wind = round((float) ($daily['wind_speed_10m_max'][$i] ?? 0));
            [$summary, $icon] = self::CODES[$code] ?? ['Unknown', '❔'];
            $risk = $this->risk($code, $rain, $wind);

            $days[] = [
                'date' => $date,
                'day' => Carbon::parse($date)->format('D, d M'),
                'summary' => $summary,
                'icon' => $icon,
                'temp_max' => round((float) ($daily['temperature_2m_max'][$i] ?? 0)),
                'temp_min' => round((float) ($daily['temperature_2m_min'][$i] ?? 0)),
                'rain_mm' => $rain,
                'wind_kmh' => $wind,
                'risk' => $risk,
                'risk_label' => ucfirst(strtolower($risk)),
                'risk_chip' => match ($risk) {
                    ShipmentException::SEVERITY_HIGH => 'chip-exception',
                    ShipmentException::SEVERITY_MEDIUM => 'chip-delay',
                    default => 'chip-delivered',
                },
            ];
        }

        return ['available' => true, 'days' => $days];
    }

    public function risk(int $code, float $rainMm, float $windKmh): string
    {
        if ($code >= 95 || $windKmh >= 60 || $rainMm >= 40) {
            return ShipmentException::SEVERITY_HIGH;
        }
        if (in_array($code, self::HEAVY_CODES, true) || $windKmh >= 40 || $rainMm >= 15) {
            return ShipmentException::SEVERITY_MEDIUM;
        }

        return ShipmentException::SEVERITY_LOW;
    }

    /** @return array{available: bool, days: array<int, never>} */
    private function unavailable(): array
    {
        return ['available' => false, 'days' => []];
    }
}
