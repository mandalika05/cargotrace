{{--
 ============================================================
 BUAT APA : Halaman detail satu shipment. Susunan dua kolom:
            KIRI  : kartu rute, TRACKING TIMELINE, item barang
            KANAN : lokasi terkini, jadwal, detail, form event / reopen
            Timeline kini ada di kolom kiri (bukan di bawah), jadi tidak ada
            jarak kosong antar kartu.
 IZIN     : Customer hanya bisa membuka shipment miliknya (ShipmentPolicy::view).
            Edit hanya muncul kalau @can('update'), Delete hanya Admin (@can('delete')).
 DATA TRACKING dihitung SEKALI di blok PHP di bawah, lalu dipakai bersama oleh
 partial tracking, tracking-location, dan tracking-actions.
 ============================================================
--}}
@extends('layouts.app')
@section('title', $shipment->shipment_number)

@section('actions')
    <a href="{{ route('shipments.index') }}" class="btn-ghost">Back to list</a>
    @can('update', $shipment)
        <a href="{{ route('shipments.edit', $shipment) }}" class="btn-primary"><x-icon name="edit" /> Edit</a>
    @endcan
    @can('delete', $shipment)
        <form method="POST" action="{{ route('shipments.destroy', $shipment) }}" onsubmit="return confirm('Delete this shipment? It will be hidden from all lists.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-danger">Delete</button>
        </form>
    @endcan
@endsection

@section('content')
@php
    // ---- Data tracking (dipakai tiga partial di bawah) ----
    $events = $shipment->trackingEvents()->with(['creator:id,name'])->chronological()->get();
    $latestId = $events->last()?->id;
    $current = $shipment->currentLocationEvent();
    $canAddEvent = auth()->user()->can('update', $shipment);
    $isMoving = in_array($shipment->status, ['PICKED_UP', 'IN_TRANSIT'], true);
    $eventTypes = collect($shipment->allowedNextStatuses())
        ->mapWithKeys(fn ($s) => [$s => ucfirst(strtolower(str_replace('_', ' ', $s)))]);
    if ($isMoving) {
        $eventTypes->put('LOCATION_UPDATE', 'Location update (no status change)');
    }
    $locations = $canAddEvent ? \App\Models\Location::orderBy('name')->get(['id', 'name', 'city', 'country']) : collect();
@endphp

<div class="detail-grid">

    {{-- ================= KOLOM KIRI ================= --}}
    <div class="detail-main">

        <div class="card panel">
            <div class="detail-head">
                <span class="chip {{ $shipment->statusChip() }}">{{ $shipment->statusLabel() }}</span>
                <span class="cell-sub">Created {{ $shipment->created_at->format('d M Y, H:i') }}@if ($shipment->creator) by {{ $shipment->creator->name }}@endif</span>
            </div>

            <div class="route-row" style="margin-top: 1.25rem">
                <div class="route-end">
                    <div class="meta-label">From</div>
                    <div class="route-city">{{ $shipment->origin->city }}</div>
                    <div class="route-place">{{ $shipment->origin->name }}, {{ $shipment->origin->country }}</div>
                </div>
                <div class="route-arrow">&rarr;</div>
                <div class="route-end">
                    <div class="meta-label">To</div>
                    <div class="route-city">{{ $shipment->destination->city }}</div>
                    <div class="route-place">{{ $shipment->destination->name }}, {{ $shipment->destination->country }}</div>
                </div>
            </div>

            <div class="progress"><div class="progress-bar" style="width: {{ $shipment->progressPercent() }}%"></div></div>
        </div>

        {{-- Timeline tracking --}}
        @include('shipments.partials.tracking')

        <div class="card panel">
            <h2 class="panel-title">Cargo items</h2>
            <div class="inner-wrap">
                <table class="inner-table">
                    <thead><tr><th>Description</th><th>Qty</th><th>Weight (kg)</th></tr></thead>
                    <tbody>
                        @foreach ($shipment->items as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td>{{ number_format($item->quantity) }}</td>
                                <td>{{ $item->weight_kg !== null ? number_format($item->weight_kg, 2) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ================= KOLOM KANAN ================= --}}
    <div class="detail-side">

        @include('shipments.partials.tracking-location')

        <div class="card panel">
            <h2 class="panel-title">Schedule</h2>
            <dl class="kv-list">
                <div><dt class="kv-key">Expected departure</dt><dd class="kv-val">{{ $shipment->expected_departure_at->format('d M Y, H:i') }}</dd></div>
                <div><dt class="kv-key">Expected arrival</dt><dd class="kv-val">{{ $shipment->expected_arrival_at->format('d M Y, H:i') }}</dd></div>
                <div><dt class="kv-key">Estimated arrival (ETA)</dt><dd class="kv-val">{{ $shipment->estimated_arrival_at?->format('d M Y, H:i') ?? '-' }}</dd></div>
                @if ($shipment->delivered_at)
                    <div><dt class="kv-key">Delivered</dt><dd class="kv-val">{{ $shipment->delivered_at->format('d M Y, H:i') }}</dd></div>
                @endif
                @if ($shipment->cancelled_at)
                    <div><dt class="kv-key">Cancelled</dt><dd class="kv-val">{{ $shipment->cancelled_at->format('d M Y, H:i') }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="card panel">
            <h2 class="panel-title">Details</h2>
            <dl class="kv-list">
                <div><dt class="kv-key">Customer</dt><dd class="kv-val">{{ $shipment->customer->name }}</dd></div>
                <div><dt class="kv-key">Carrier</dt><dd class="kv-val">{{ $shipment->carrier->name }} ({{ $shipment->carrier->code }})</dd></div>
                <div><dt class="kv-key">Cargo type</dt><dd class="kv-val">{{ $shipment->cargo_type }}</dd></div>
                <div><dt class="kv-key">Weight</dt><dd class="kv-val">{{ number_format($shipment->weight_kg, 2) }} kg</dd></div>
                <div><dt class="kv-key">Volume</dt><dd class="kv-val">{{ number_format($shipment->volume_cbm, 2) }} m&sup3;</dd></div>
            </dl>
        </div>

        @include('shipments.partials.tracking-actions')
    </div>
</div>
@endsection
