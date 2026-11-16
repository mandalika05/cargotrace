<?php
/*
 * ============================================================
 * BUAT APA : REST API ringkasan dashboard, data pilihan (carrier, lokasi) untuk staf, dan pengaturan ambang delay.
 * ============================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingsRequest;
use App\Http\Resources\CarrierResource;
use App\Http\Resources\LocationResource;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Setting;
use App\Models\Shipment;
use App\Services\EtaService;
use App\Services\ExceptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    // GET /api/v1/dashboard  (customer: hanya angka miliknya)
    public function dashboard(Request $request, ExceptionService $exceptions): JsonResponse
    {
        $user = $request->user();
        $base = Shipment::query();
        if ($user->isCustomer()) {
            $base->where('customer_id', $user->id);
        }

        $byStatus = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $statuses = collect(Shipment::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($byStatus[$s] ?? 0)]);

        return response()->json(['data' => [
            'total' => (int) $statuses->sum(),
            'by_status' => $statuses,
            'delayed' => (clone $base)->delayed()->count(),
            'exceptions' => $exceptions->summary($user),
        ]]);
    }

    // GET /api/v1/carriers  (admin, operator)
    public function carriers()
    {
        return CarrierResource::collection(Carrier::orderBy('name')->get());
    }

    // GET /api/v1/locations  (admin, operator)
    public function locations()
    {
        return LocationResource::collection(Location::orderBy('name')->get());
    }

    // GET /api/v1/settings/delay  (admin, operator)
    public function delaySettings(EtaService $eta): JsonResponse
    {
        return response()->json(['data' => $eta->thresholds()]);
    }

    // PUT /api/v1/settings/delay  (admin)  {delay_low_max_hours, delay_medium_max_hours}
    public function updateDelaySettings(SettingsRequest $request, EtaService $eta): JsonResponse
    {
        foreach ($request->validated() as $key => $value) {
            Setting::putValue($key, $value);
        }

        return response()->json(['data' => $eta->thresholds()]);
    }
}
