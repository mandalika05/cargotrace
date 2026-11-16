<?php
/*
 * ============================================================
 * BUAT APA : REST API tracking: timeline, tambah event (staf), buka kembali shipment (admin). Logika di TrackingService (state machine) yang sama dengan web.
 * ============================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrackingEventRequest;
use App\Http\Resources\TrackingEventResource;
use App\Models\Shipment;
use App\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TrackingController extends Controller
{
    public function __construct(private readonly TrackingService $tracking)
    {
    }

    // GET /api/v1/shipments/{id}/tracking  -> timeline berurutan waktu + lokasi terkini
    public function index(Shipment $shipment): JsonResponse
    {
        Gate::authorize('view', $shipment);

        $events = $shipment->trackingEvents()->with('creator:id,name')->chronological()->get();
        $current = $shipment->currentLocationEvent();

        return response()->json([
            'data' => TrackingEventResource::collection($events),
            'meta' => [
                'status' => $shipment->status,
                'current_location' => $current ? [
                    'name' => $current->placeLabel(),
                    'latitude' => $current->latitude !== null ? (float) $current->latitude : null,
                    'longitude' => $current->longitude !== null ? (float) $current->longitude : null,
                    'as_of' => $current->occurred_at?->toIso8601String(),
                ] : null,
            ],
        ]);
    }

    // POST /api/v1/shipments/{id}/tracking  (admin, operator)
    public function store(TrackingEventRequest $request, Shipment $shipment): JsonResponse
    {
        $event = $this->tracking->addEvent($shipment, $request->validated(), $request->user());

        return (new TrackingEventResource($event))->response()->setStatusCode(201);
    }

    // POST /api/v1/shipments/{id}/reopen  (admin) {reason}
    public function reopen(Request $request, Shipment $shipment): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        // 200 (bukan 201): membuka kembali adalah aksi pada shipment yang sudah ada
        return (new TrackingEventResource($this->tracking->reopen($shipment, $request->user(), $data['reason'])))
            ->response()->setStatusCode(200);
    }
}
