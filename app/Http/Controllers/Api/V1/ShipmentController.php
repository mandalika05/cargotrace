<?php
/*
 * ============================================================
 * BUAT APA : REST API shipment: daftar (search/filter/sort/pagination), buat, lihat, ubah, hapus. Memakai ShipmentService, ShipmentRequest dan ShipmentPolicy yang SAMA dengan web.
 * ============================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShipmentRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ShipmentController extends Controller
{
    private const WITH = ['customer:id,name', 'carrier:id,name,code,is_active', 'origin', 'destination', 'items'];

    public function __construct(private readonly ShipmentService $shipments)
    {
    }

    // GET /api/v1/shipments?q=&status=&carrier_id=&sort=&per_page=
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Shipment::class);

        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        return ShipmentResource::collection(
            $this->shipments->paginate($request->user(), $request->only(['q', 'status', 'carrier_id', 'sort']), $perPage)
        );
    }

    // GET /api/v1/shipments/{id}
    public function show(Shipment $shipment): ShipmentResource
    {
        Gate::authorize('view', $shipment); // customer lain -> 403

        return new ShipmentResource($this->load($shipment));
    }

    // POST /api/v1/shipments  (admin, operator)
    public function store(ShipmentRequest $request): JsonResponse
    {
        $shipment = $this->shipments->create($request->shipmentData(), $request->items(), $request->user());

        return (new ShipmentResource($this->load($shipment)))->response()->setStatusCode(201);
    }

    // PUT /api/v1/shipments/{id}  (admin, operator; shipment belum selesai)
    public function update(ShipmentRequest $request, Shipment $shipment): ShipmentResource
    {
        $shipment = $this->shipments->update($shipment, $request->shipmentData(), $request->items());

        return new ShipmentResource($this->load($shipment));
    }

    // DELETE /api/v1/shipments/{id}  (admin)
    public function destroy(Shipment $shipment): Response
    {
        Gate::authorize('delete', $shipment);
        $this->shipments->delete($shipment);

        return response()->noContent();
    }

    private function load(Shipment $shipment): Shipment
    {
        return $shipment->load(self::WITH)->loadCount('unresolvedExceptions');
    }
}
