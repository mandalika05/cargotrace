<?php
/*
 * ============================================================
 * BUAT APA : REST API delay dan ETA: lihat penilaian delay (alasan + nilai), perbarui ETA (staf, dengan alasan), serta antrean Needs attention (staf).
 * ============================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEtaRequest;
use App\Http\Resources\DelayResource;
use App\Http\Resources\TrackingEventResource;
use App\Models\Shipment;
use App\Services\AttentionService;
use App\Services\EtaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DelayController extends Controller
{
    // GET /api/v1/shipments/{id}/delay
    public function show(Shipment $shipment): DelayResource
    {
        Gate::authorize('view', $shipment);

        return new DelayResource($shipment->delay());
    }

    // PATCH /api/v1/shipments/{id}/eta  {estimated_arrival_at, reason}  (admin, operator)
    public function updateEta(UpdateEtaRequest $request, Shipment $shipment, EtaService $eta): JsonResponse
    {
        $event = $eta->updateEta($shipment, $request->validated('estimated_arrival_at'), $request->validated('reason'), $request->user());

        return response()->json([
            'delay' => new DelayResource($shipment->refresh()->delay()),
            'event' => new TrackingEventResource($event),
        ]);
    }

    // GET /api/v1/attention  (admin, operator): shipment telat / bermasalah, paling gawat dulu
    public function attention(AttentionService $attention): JsonResponse
    {
        $data = $attention->queue(50)->map(fn (array $e) => [
            'shipment_id' => $e['shipment']->id,
            'shipment_number' => $e['shipment']->shipment_number,
            'status' => $e['shipment']->status,
            'route' => $e['shipment']->origin->city.' -> '.$e['shipment']->destination->city,
            'priority' => $e['priority'],
            'score' => $e['score'],
            'reasons' => $e['reasons'],
            'delay' => new DelayResource($e['delay']),
        ])->values();

        return response()->json(['data' => $data]);
    }
}
