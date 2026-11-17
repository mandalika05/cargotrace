{{--
 ============================================================
 BUAT APA : Baris tiga kartu di bawah kartu angka, meniru mockup dosen:
            1) Shipments by Status  : grafik batang berwarna + kotak "Total shipments"
            2) Delay Rate by Carrier: donut, angka rata-rata delay di tengah, legenda persen
            3) Shipment Map         : peta posisi terkini shipment yang masih berjalan
 DATA     : $insights['charts'] dari DashboardService (angka asli dari database, sesuai peran).
            Peta diambil lewat JavaScript dari /dashboard/map (resources/js/dashboard-map.js).
 ============================================================
--}}
@php
    $status = $insights['charts']['status'];
    $donut = $insights['charts']['delay_by_carrier'];
    $center = $donut['center'] === null ? '-' : number_format($donut['center'], 1).'%';
@endphp

<div class="dash-row">

    {{-- 1) Shipments by Status --}}
    <div class="card chart-card">
        <h2 class="panel-title">Shipments by Status</h2>
        <p class="chart-sub">Overview of shipments by status</p>
        @if ($status['total'] === 0)
            <p class="chart-empty">No data to chart yet.</p>
        @else
            <div class="chart-box chart-box-sm">
                <canvas data-chart="{{ json_encode(['type' => 'bar'] + $status) }}" role="img" aria-label="Shipments by status"></canvas>
            </div>
            <div class="total-pill"><strong>{{ number_format($status['total']) }}</strong> Total shipments</div>
        @endif
    </div>

    {{-- 2) Delay Rate by Carrier --}}
    <div class="card chart-card">
        <h2 class="panel-title">Delay Rate by Carrier</h2>
        <p class="chart-sub">Distribution of delayed shipments across carriers</p>
        @if ($donut['total'] === 0)
            <p class="chart-empty">No delayed shipments. All good!</p>
        @else
            <div class="donut-layout">
                <div class="donut-wrap">
                    <div class="chart-box">
                        <canvas data-chart="{{ json_encode(['type' => 'doughnut', 'legend' => false, 'labels' => $donut['labels'], 'values' => $donut['values'], 'colors' => $donut['colors']]) }}"
                                role="img" aria-label="Delayed shipments by carrier"></canvas>
                    </div>
                    <div class="donut-center"><strong>{{ $center }}</strong><span>Average delay rate</span></div>
                </div>
                <ul class="donut-legend">
                    @foreach ($donut['labels'] as $i => $label)
                        <li>
                            <span class="dot" style="background: {{ $donut['colors'][$i] }}"></span>
                            <span class="name">{{ $label }}</span>
                            <span class="pct">{{ rtrim(rtrim(number_format($donut['percents'][$i], 1), '0'), '.') }}%</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    {{-- 3) Shipment Map --}}
    <div class="card chart-card dash-map-card">
        <h2 class="panel-title">Shipment Map</h2>
        <p class="chart-sub">Latest position of running shipments</p>
        <div id="dashboard-map" class="map-box dash-map"
             data-url="{{ route('dashboard.map') }}"
             data-tiles="{{ config('cargotrace.map.tile_url') }}">
            <p class="map-message">Loading map&hellip;</p>
        </div>
    </div>
</div>
