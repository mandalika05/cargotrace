<?php
/*
 * ============================================================
 * BUAT APA : Kelola carrier (khusus Admin; dijaga middleware role:admin di route).
 * HAPUS    : carrier yang masih dipakai shipment tidak boleh dihapus
 *            (disarankan dinonaktifkan). Database juga menjaga lewat restrictOnDelete.
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\CarrierRequest;
use App\Models\Carrier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CarrierController extends Controller
{
    public function index(Request $request): View
    {
        $carriers = Carrier::query()
            ->withCount('shipments')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.addcslashes($request->string('q')->toString(), '%_\\').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('code', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('carriers.index', ['carriers' => $carriers, 'q' => $request->string('q')->toString()]);
    }

    public function create(): View
    {
        return view('carriers.form', ['carrier' => new Carrier(['is_active' => true])]);
    }

    public function store(CarrierRequest $request): RedirectResponse
    {
        Carrier::create($request->validated());

        return redirect()->route('carriers.index')->with('success', 'Carrier created.');
    }

    public function edit(Carrier $carrier): View
    {
        return view('carriers.form', ['carrier' => $carrier]);
    }

    public function update(CarrierRequest $request, Carrier $carrier): RedirectResponse
    {
        $carrier->update($request->validated());

        return redirect()->route('carriers.index')->with('success', 'Carrier updated.');
    }

    public function destroy(Carrier $carrier): RedirectResponse
    {
        if ($carrier->shipments()->withTrashed()->exists()) {
            return back()->with('error', 'This carrier is used by shipments. Deactivate it instead of deleting it.');
        }

        $carrier->delete();

        return redirect()->route('carriers.index')->with('success', 'Carrier deleted.');
    }
}
