<?php
/*
 * ============================================================
 * BUAT APA : Prakiraan cuaca satu shipment dalam bentuk JSON. Dipakai kartu cuaca di
 *            halaman detail (dimuat belakangan lewat JavaScript, jadi halaman tetap
 *            cepat walau internet lambat) dan oleh REST API.
 * IZIN     : sama dengan melihat shipment (ShipmentPolicy::view), customer lain -> 403.
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class WeatherController extends Controller
{
    public function show(Shipment $shipment, WeatherService $weather): JsonResponse
    {
        Gate::authorize('view', $shipment);

        return response()->json(['data' => $weather->forShipment($shipment->loadMissing('destination'))]);
    }
}
