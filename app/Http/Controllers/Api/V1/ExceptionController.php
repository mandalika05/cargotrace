<?php
/*
 * ============================================================
 * BUAT APA : REST API exception: daftar, per shipment, catat, ubah status. Aturan di ExceptionService + ShipmentExceptionPolicy yang sama dengan web.
 * ============================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExceptionRequest;
use App\Http\Requests\UpdateExceptionStatusRequest;
use App\Http\Resources\ExceptionResource;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Services\ExceptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExceptionController extends Controller
{
    public function __construct(private readonly ExceptionService $exceptions)
    {
    }

    // GET /api/v1/exceptions?status=&severity=&type=&q=&per_page=  (customer: hanya miliknya)
    public function index(Request $request)
    {
        Gate::authorize('viewAny', ShipmentException::class);

        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        return ExceptionResource::collection(
            $this->exceptions->paginate($request->user(), $request->only(['q', 'status', 'severity', 'type']), $perPage)
        );
    }

    // GET /api/v1/shipments/{id}/exceptions
    public function forShipment(Shipment $shipment)
    {
        Gate::authorize('view', $shipment);

        return ExceptionResource::collection(
            $shipment->exceptions()->with(['reporter:id,name', 'resolver:id,name'])->orderByDesc('detected_at')->orderByDesc('id')->get()
        );
    }

    // POST /api/v1/shipments/{id}/exceptions  (admin, operator)
    public function store(StoreExceptionRequest $request, Shipment $shipment): JsonResponse
    {
        $exception = $this->exceptions->report($shipment, $request->validated(), $request->user());

        return (new ExceptionResource($exception->load(['reporter:id,name'])))->response()->setStatusCode(201);
    }

    // PATCH /api/v1/exceptions/{id}  {status: INVESTIGATING|RESOLVED, note?}
    public function update(UpdateExceptionStatusRequest $request, ShipmentException $exception): ExceptionResource
    {
        $exception = $this->exceptions->changeStatus($exception, $request->validated('status'), $request->user(), $request->validated('note'));

        return new ExceptionResource($exception->load(['reporter:id,name', 'resolver:id,name']));
    }
}
