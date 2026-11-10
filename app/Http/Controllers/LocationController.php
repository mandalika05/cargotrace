<?php
/*
 * ============================================================
 * BUAT APA : Kelola lokasi (khusus Admin). Sama polanya dengan CarrierController.
 * HAPUS    : lokasi yang masih dipakai sebagai asal/tujuan shipment ditolak.
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\LocationRequest;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $locations = Location::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.addcslashes($request->string('q')->toString(), '%_\\').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)
                    ->orWhere('city', 'like', $term)
                    ->orWhere('country', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('locations.index', ['locations' => $locations, 'q' => $request->string('q')->toString()]);
    }

    public function create(): View
    {
        return view('locations.form', ['location' => new Location()]);
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        Location::create($request->validated());

        return redirect()->route('locations.index')->with('success', 'Location created.');
    }

    public function edit(Location $location): View
    {
        return view('locations.form', ['location' => $location]);
    }

    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        $location->update($request->validated());

        return redirect()->route('locations.index')->with('success', 'Location updated.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        $used = $location->originShipments()->withTrashed()->exists()
            || $location->destinationShipments()->withTrashed()->exists();

        if ($used) {
            return back()->with('error', 'This location is used by shipments and cannot be deleted.');
        }

        $location->delete();

        return redirect()->route('locations.index')->with('success', 'Location deleted.');
    }
}
