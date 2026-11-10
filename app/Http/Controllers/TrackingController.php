<?php
/*
 * ============================================================
 * BUAT APA : Menambah tracking event dan membuka kembali shipment (Admin).
 * CONTROLLER TIPIS: izin dicek di Form Request / route, lalu TrackingService
 * yang bekerja.
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\TrackingEventRequest;
use App\Models\Shipment;
use App\Services\TrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function __construct(private readonly TrackingService $tracking)
    {
    }

    public function store(TrackingEventRequest $request, Shipment $shipment): RedirectResponse
    {
        $this->tracking->addEvent($shipment, $request->validated(), $request->user());

        return redirect()->to(route('shipments.show', $shipment).'#timeline')->with('success', 'Tracking event added.');
    }

    // Hanya Admin (dijaga middleware role:admin di routes/tracking.php)
    public function reopen(Request $request, Shipment $shipment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        $this->tracking->reopen($shipment, $request->user(), $data['reason']);

        return redirect()->to(route('shipments.show', $shipment).'#timeline')->with('success', 'Shipment reopened.');
    }
}
