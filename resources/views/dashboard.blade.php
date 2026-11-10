{{--
 ============================================================
 BUAT APA : Dashboard sementara dengan angka NYATA dari database.
            Dashboard lengkap (grafik, peta, needs attention) dibuat di P13.
 RESPONSIF: kartu angka 2 kolom di HP, 4 kolom di laptop.
 ============================================================
--}}
@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    <div class="card welcome-card">
        <p class="eyebrow">Signed in as {{ auth()->user()->role }}</p>
        <h2 class="welcome-title">Welcome, {{ auth()->user()->name }}</h2>
        <p class="welcome-text">
            @if (auth()->user()->isCustomer())
                Follow your shipments, see where they are and when they arrive.
            @else
                Your control tower for shipments, tracking, delays and exceptions.
            @endif
        </p>
    </div>

    {{-- ===== Kartu angka ===== --}}
    <div class="stat-grid">
        <div class="card stat-card">
            <div class="stat-icon stat-icon-total"><x-icon name="package" class="icon-lg" /></div>
            <div><div class="stat-label">Total shipments</div><div class="stat-value">{{ number_format($stats['total']) }}</div></div>
        </div>
        <div class="card stat-card">
            <div class="stat-icon stat-icon-transit"><x-icon name="truck" class="icon-lg" /></div>
            <div><div class="stat-label">In transit</div><div class="stat-value">{{ number_format($stats['in_transit']) }}</div></div>
        </div>
        <div class="card stat-card">
            <div class="stat-icon stat-icon-delivered"><x-icon name="check-circle" class="icon-lg" /></div>
            <div><div class="stat-label">Delivered</div><div class="stat-value">{{ number_format($stats['delivered']) }}</div></div>
        </div>
        <div class="card stat-card">
            <div class="stat-icon stat-icon-pending"><x-icon name="clock" class="icon-lg" /></div>
            <div><div class="stat-label">Awaiting pickup</div><div class="stat-value">{{ number_format($stats['pending']) }}</div></div>
        </div>
    </div>

    {{-- ===== Shipment terbaru ===== --}}
    <div class="page-body">
        <h2 class="panel-title">Recent shipments</h2>
        @if ($recent->isEmpty())
            <div class="empty-state">
                <p class="empty-title">No shipments yet</p>
                <p>Shipments will appear here as soon as they are created.</p>
            </div>
        @else
            <div class="card recent-list">
                @foreach ($recent as $shipment)
                    <a href="{{ route('shipments.show', $shipment) }}" class="recent-row">
                        <div>
                            <div class="item-card-title">{{ $shipment->shipment_number }}</div>
                            <div class="cell-sub">{{ $shipment->origin->city }} &rarr; {{ $shipment->destination->city }}</div>
                        </div>
                        <span class="chip {{ $shipment->statusChip() }}">{{ $shipment->statusLabel() }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endsection
