<?php
/*
 * ============================================================
 * BUAT APA : Halaman shipment (daftar, detail, buat, ubah, hapus).
 * CONTROLLER TIPIS: hanya (1) cek izin, (2) panggil ShipmentService,
 *                   (3) kembalikan halaman/redirect. Logika bisnis ada di Service.
 * Gate::authorize(...) memanggil ShipmentPolicy; kalau tidak boleh -> 403.
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\ShipmentFilterRequest;
use App\Http\Requests\ShipmentRequest;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function __construct(private readonly ShipmentService $shipments)
    {
    }

    // Daftar + search + filter + sort + pagination
    public function index(ShipmentFilterRequest $request): View
    {
        Gate::authorize('viewAny', Shipment::class);

        // Hanya filter yang LOLOS validasi (lihat ShipmentFilterRequest)
        $filters = $request->validated();

        return view('shipments.index', [
            'shipments' => $this->shipments->paginate($request->user(), $filters),
            'carriers' => Carrier::orderBy('name')->get(['id', 'name']),
            'locations' => $this->filterLocations($request->user()),
            'filters' => $filters,
        ]);
    }

    // Pilihan Origin/Destination di filter. Customer hanya melihat lokasi dari shipment miliknya.
    private function filterLocations(User $user)
    {
        $query = Location::orderBy('name');

        if ($user->isCustomer()) {
            $own = Shipment::where('customer_id', $user->id)->get(['origin_id', 'destination_id']);
            $query->whereIn('id', $own->pluck('origin_id')->merge($own->pluck('destination_id'))->unique()->all());
        }

        return $query->get(['id', 'name', 'city']);
    }

    public function create(): View
    {
        Gate::authorize('create', Shipment::class);

        return view('shipments.form', ['shipment' => new Shipment()] + $this->formOptions());
    }

    public function store(ShipmentRequest $request): RedirectResponse
    {
        // Izin 'create' sudah dicek di ShipmentRequest::authorize()
        $shipment = $this->shipments->create($request->shipmentData(), $request->items(), $request->user());

        return redirect()->route('shipments.show', $shipment)->with('success', 'Shipment created.');
    }

    public function show(Shipment $shipment): View
    {
        Gate::authorize('view', $shipment); // customer lain -> 403

        $shipment->load(['customer', 'carrier', 'origin', 'destination', 'items', 'creator']);

        return view('shipments.show', ['shipment' => $shipment]);
    }

    public function edit(Shipment $shipment): View
    {
        Gate::authorize('update', $shipment); // shipment terminal -> 403

        $shipment->load('items');

        return view('shipments.form', ['shipment' => $shipment] + $this->formOptions());
    }

    public function update(ShipmentRequest $request, Shipment $shipment): RedirectResponse
    {
        $this->shipments->update($shipment, $request->shipmentData(), $request->items());

        return redirect()->route('shipments.show', $shipment)->with('success', 'Shipment updated.');
    }

    public function destroy(Shipment $shipment): RedirectResponse
    {
        Gate::authorize('delete', $shipment); // hanya admin

        $this->shipments->delete($shipment);

        return redirect()->route('shipments.index')->with('success', 'Shipment deleted.');
    }

    // Pilihan dropdown untuk form
    private function formOptions(): array
    {
        return [
            'customers' => User::where('role', User::ROLE_CUSTOMER)->orderBy('name')->get(['id', 'name', 'email']),
            'carriers' => Carrier::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'locations' => Location::orderBy('name')->get(),
        ];
    }
}
