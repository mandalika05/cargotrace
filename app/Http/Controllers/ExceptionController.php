<?php
/*
 * ============================================================
 * BUAT APA : Halaman exception: daftar, catat baru, ubah status.
 * CONTROLLER TIPIS: izin dicek di Form Request / Policy, kerja dilakukan ExceptionService.
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\StoreExceptionRequest;
use App\Http\Requests\UpdateExceptionStatusRequest;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Services\ExceptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ExceptionController extends Controller
{
    public function __construct(private readonly ExceptionService $exceptions)
    {
    }

    // Daftar + filter (customer: hanya exception shipment miliknya)
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ShipmentException::class);

        return view('exceptions.index', [
            'exceptions' => $this->exceptions->paginate($request->user(), $request->only(['q', 'status', 'severity', 'type'])),
            'summary' => $this->exceptions->summary($request->user()),
            'filters' => $request->only(['q', 'status', 'severity', 'type']),
        ]);
    }

    public function store(StoreExceptionRequest $request, Shipment $shipment): RedirectResponse
    {
        $this->exceptions->report($shipment, $request->validated(), $request->user());

        return redirect()->to(route('shipments.show', $shipment).'#exceptions')->with('success', 'Exception reported.');
    }

    public function update(UpdateExceptionStatusRequest $request, ShipmentException $exception): RedirectResponse
    {
        $this->exceptions->changeStatus($exception, $request->validated('status'), $request->user(), $request->validated('note'));

        return redirect()->to(route('shipments.show', $exception->shipment_id).'#exceptions')->with('success', 'Exception updated.');
    }
}
