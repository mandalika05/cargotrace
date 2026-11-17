{{--
 ============================================================
 BUAT APA : Bagian "Performance insights" di bagian bawah dashboard (P13): 5 KPI kelompok
            (on-time rate, delay rate, exception rate, rata-rata delay, rata-rata penyelesaian
            exception) + grafik tren bulanan dan exception per jenis.
 DATA     : $insights dari DashboardService (angka dihitung dari database, sesuai peran).
 GRAFIK   : Chart.js (resources/js/dashboard-charts.js), data lewat atribut data-chart.
            Data kosong -> pesan, bukan grafik kosong.
 KPI      : tanda "-" bila belum ada data (tidak ada pembagian dengan nol).
 ============================================================
--}}
@php
    $k = $insights['kpis'];
    $c = $insights['charts'];
    $pct = fn ($v) => $v === null ? '-' : number_format($v, 1).'%';
    $dur = fn ($m) => $m === null ? '-' : \App\Services\DelayAssessment::formatMinutes($m);

    // Dua grafik tambahan (status dan donut carrier ada di dashboard/overview)
    $charts = [
        ['title' => 'Shipments created per month', 'sub' => 'New shipments over the last 6 months', 'type' => 'line', 'data' => $c['trend']],
        ['title' => 'Exceptions by type', 'sub' => 'What goes wrong most often', 'type' => 'bar', 'horizontal' => true, 'data' => $c['exception_type']],
    ];
@endphp

<div class="page-body">
    <h2 class="panel-title">Performance insights</h2>

    <div class="kpi-grid">
        <div class="card kpi-card">
            <div class="kpi-label">On-time delivery rate</div>
            <div class="kpi-value">{{ $pct($k['on_time_rate']) }}</div>
            <div class="kpi-sub">of {{ number_format($k['delivered']) }} delivered</div>
        </div>
        <div class="card kpi-card">
            <div class="kpi-label">Delay rate</div>
            <div class="kpi-value">{{ $pct($k['delay_rate']) }}</div>
            <div class="kpi-sub">of {{ number_format($k['active']) }} active or finished shipments</div>
        </div>
        <div class="card kpi-card">
            <div class="kpi-label">Exception rate</div>
            <div class="kpi-value">{{ $pct($k['exception_rate']) }}</div>
            <div class="kpi-sub">shipments with at least one exception</div>
        </div>
        <div class="card kpi-card">
            <div class="kpi-label">Average delay</div>
            <div class="kpi-value">{{ $dur($k['avg_delay_minutes']) }}</div>
            <div class="kpi-sub">across delayed shipments</div>
        </div>
        <div class="card kpi-card">
            <div class="kpi-label">Avg. exception resolution</div>
            <div class="kpi-value">{{ $dur($k['avg_resolution_minutes']) }}</div>
            <div class="kpi-sub">from detection to resolved</div>
        </div>
    </div>

    <div class="chart-grid">
        @foreach ($charts as $chart)
            <div class="card chart-card">
                <h2 class="panel-title">{{ $chart['title'] }}</h2>
                <p class="chart-sub">{{ $chart['sub'] }}</p>
                @if (array_sum($chart['data']['values']) === 0)
                    <p class="chart-empty">No data to chart yet.</p>
                @else
                    <div class="chart-box">
                        <canvas data-chart="{{ json_encode(['type' => $chart['type'], 'horizontal' => $chart['horizontal'] ?? false] + $chart['data']) }}"
                                role="img" aria-label="{{ $chart['title'] }}"></canvas>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
