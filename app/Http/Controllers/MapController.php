<?php
/*
 * ============================================================
 * BUAT APA : Data peta satu shipment dalam bentuk JSON. Dipakai kartu peta di halaman
 *            detail (diambil lewat JavaScript) dan oleh REST API.
 * IZIN     : sama dengan melihat shipment (ShipmentPolicy::view): customer hanya bisa
 *            melihat peta shipment miliknya, milik orang lain -> 403.
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\MapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MapController extends Controller
{
    public function show(Shipment $shipment, MapService $map): JsonResponse
    {
        Gate::authorize('view', $shipment);

        return response()->json(['data' => $map->forShipment($shipment)]);
    }
}
