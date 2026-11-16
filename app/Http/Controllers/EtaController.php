<?php
/*
 * ============================================================
 * BUAT APA : Staf memperbarui ETA shipment (dengan alasan). Logika di EtaService.
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEtaRequest;
use App\Models\Shipment;
use App\Services\EtaService;
use Illuminate\Http\RedirectResponse;

class EtaController extends Controller
{
    public function __construct(private readonly EtaService $eta)
    {
    }

    public function update(UpdateEtaRequest $request, Shipment $shipment): RedirectResponse
    {
        $this->eta->updateEta($shipment, $request->validated('estimated_arrival_at'), $request->validated('reason'), $request->user());

        return redirect()->to(route('shipments.show', $shipment).'#delay')->with('success', 'ETA updated.');
    }
}
