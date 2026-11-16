<?php
/*
 * ============================================================
 * BUAT APA : Mencari koordinat dari nama tempat (GET /geocode?q=...). Dipanggil tombol
 *            "Find coordinates" di form Lokasi dan form Tracking event, dan oleh API.
 * IZIN     : hanya Admin dan Operator (dijaga middleware role di route).
 * VALIDASI : q wajib, 3 sampai 150 karakter.
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Services\GeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeocodeController extends Controller
{
    public function search(Request $request, GeocodingService $geocoding): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:3', 'max:150']]);

        return response()->json(['data' => $geocoding->search($data['q'])]);
    }
}
