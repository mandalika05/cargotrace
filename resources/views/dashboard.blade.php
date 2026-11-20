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
        {{-- Sapaan sesuai jam di perangkat pengunjung (resources/js/greeting.js). Tulisan "Welcome" = cadangan bila JavaScript mati. --}}
        <h2 class="welcome-title"><span data-greeting>Welcome</span>, {{ auth()->user()->name }}</h2>
        <p class="welcome-text">
            @if (auth()->user()->isCustomer())
                Follow your shipments, see where they are and when they arrive.
            @else
                Your control tower for shipments, tracking, delays and exceptions.
            @endif
        </p>
    </div>

    {{-- ===== Kartu angka ===== --}}
    <div class="stat-grid stat-grid-6">
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
        <div class="card stat-card">
            <div class="stat-icon stat-icon-delay"><x-icon name="clock" class="icon-lg" /></div>
            <div><div class="stat-label">Delayed</div><div class="stat-value">{{ number_format($stats['delayed']) }}</div></div>
        </div>
        <div class="card stat-card">
            <div class="stat-icon stat-icon-exception"><x-icon name="alert-triangle" class="icon-lg" /></div>
            <div><div class="stat-label">Open exceptions</div><div class="stat-value">{{ number_format($stats['open_exceptions']) }}</div></div>
        </div>
    </div>

    {{-- ===== Status, delay per carrier, dan peta (meniru mockup dosen, P13) ===== --}}
    @include('dashboard.overview')

    {{-- ===== Needs attention (staf) ===== --}}
    @unless (auth()->user()->isCustomer())
        <div class="page-body">
            <h2 class="panel-title">Needs attention</h2>
            @if ($attention->isEmpty())
                <div class="empty-state"><p class="empty-title">All clear</p><p>No delayed shipment or open exception right now.</p></div>
            @else
                <div class="attn-list">
                    @foreach ($attention as $entry)
                        @php $shipment = $entry['shipment']; $p = strtolower($entry['priority']); @endphp
                        <a href="{{ route('shipments.show', $shipment) }}" class="attn-row attn-{{ $p }}">
                            <div><span class="attn-rank attn-rank-{{ $p }}">{{ $entry['priority'] }}</span></div>
                            <div>
                                <div class="attn-title">{{ $shipment->shipment_number }}</div>
                                <div class="attn-route">{{ $shipment->origin->city }} &rarr; {{ $shipment->destination->city }} &middot; {{ $shipment->customer->name }}</div>
                                <ul class="attn-reasons">@foreach ($entry['reasons'] as $reason) <li>{{ $reason }}</li> @endforeach</ul>
                            </div>
                            <div class="attn-score">Score<strong>{{ $entry['score'] }}</strong></div>
                        </a>
                    @endforeach
                </div>
                <p class="cell-sub" style="margin-top:.75rem"><a href="{{ route('attention.index') }}" class="link-accent">See the full queue &rarr;</a></p>
            @endif
        </div>
    @endunless

    {{-- ===== Shipment terbaru: tabel di laptop, daftar ringkas di HP (meniru mockup dosen) ===== --}}
    <div class="page-body">
        <div class="recent-head">
            <div>
                <h2 class="panel-title">Recent Shipments</h2>
                <p class="chart-sub">Live overview of the latest shipments</p>
            </div>
            <div class="recent-actions">
                <a href="{{ route('shipments.index') }}" class="btn-ghost"><x-icon name="sliders" /> Filter</a>
                <a href="{{ route('exports.shipments') }}" class="btn-ghost"><x-icon name="file-text" /> Export</a>
            </div>
        </div>

        @if ($recent->isEmpty())
            <div class="empty-state">
                <p class="empty-title">No shipments yet</p>
                <p>Shipments will appear here as soon as they are created.</p>
            </div>
        @else
            <div class="only-desktop">
                <div class="table-wrap">
                    <table class="data-table table-fit">
                        <thead>
                            <tr>
                                <th>Shipment No.</th>
                                <th>Customer</th>
                                <th>Carrier</th>
                                <th>Origin</th>
                                <th>Destination</th>
                                <th>ETA</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recent as $shipment)
                                <tr>
                                    <td><a href="{{ route('shipments.show', $shipment) }}" class="cell-link">{{ $shipment->shipment_number }}</a></td>
                                    <td>{{ $shipment->customer->name }}</td>
                                    <td>{{ $shipment->carrier->name }}</td>
                                    <td>{{ $shipment->origin->city }}</td>
                                    <td>{{ $shipment->destination->city }}</td>
                                    <td>{{ $shipment->estimated_arrival_at?->format('d M Y') }}</td>
                                    <td class="cell-status">
                                        <span class="chip {{ $shipment->statusChip() }}">{{ $shipment->statusLabel() }}</span>
                                        @php $delay = $shipment->delay(); @endphp
                                        @if ($delay->isDelayed()) <div style="margin-top:.35rem"><span class="chip {{ $delay->chipClass() }}">{{ $delay->label() }}</span></div> @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="only-mobile card recent-list">
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

    {{-- ===== KPI kelompok dan grafik tambahan (P13) ===== --}}
    @include('dashboard.insights')
@endsection
