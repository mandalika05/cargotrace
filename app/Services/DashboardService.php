<?php
/*
 * ============================================================
 * BUAT APA : Menghitung angka KPI dan data GRAFIK untuk dashboard (syarat brief:
 *            dashboard dengan grafik, delay rate, exception rate, KPI kelompok).
 *            Semua angka dihitung dari database, tidak ada angka statis.
 * LINGKUP  : Customer hanya menghitung shipment dan exception MILIKNYA; staf menghitung semua.
 *
 * KPI (hasil 'kpis'):
 *   on_time_rate            % shipment DELIVERED yang tiba tepat waktu (null bila belum ada)
 *   delay_rate              % shipment (tidak dibatalkan) yang terlambat: yang sedang terlambat
 *                           + yang sudah tiba terlambat. Aturannya dari EtaService::assess().
 *   exception_rate          % shipment (tidak dibatalkan) yang pernah punya exception
 *   avg_delay_minutes       rata-rata lama keterlambatan (menit) dari shipment yang terlambat
 *   avg_resolution_minutes  rata-rata waktu penyelesaian exception (detected -> resolved)
 *   Bila pembaginya 0 (belum ada data), hasilnya null; tampilan menulis "-" (tidak error).
 *
 * GRAFIK (hasil 'charts'), meniru mockup dashboard dosen:
 *   status           5 batang berwarna: Awaiting pickup, In transit, Delivered, Delayed,
 *                    Open exceptions (+ 'total' semua shipment) -> grafik batang
 *   delay_by_carrier pembagian shipment TERLAMBAT per carrier dalam persen (5 teratas,
 *                    sisanya "Others"), 'center' = delay rate keseluruhan -> donut
 *   exception_type   jumlah exception per jenis -> batang mendatar
 *   trend            jumlah shipment dibuat per bulan, 6 bulan terakhir -> garis
 *   'colors' dikirim dari sini, jadi warna grafik, legenda, dan angka selalu sama.
 * ============================================================
 */

namespace App\Services;

use App\Models\Carrier;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DashboardService
{
    public function __construct(
        private readonly EtaService $eta,
        private readonly ExceptionService $exceptions,
    ) {
    }

    // Warna-warni grafik (biru, hijau, oranye, merah, ungu, teal, emas, abu)
    public const PALETTE = ['#2b7fb5', '#1f9d6b', '#e8892a', '#d64545', '#8b6fd1', '#14b8a6', '#f4b740', '#8fa39b'];

    /**
     * @return array{kpis: array<string, mixed>, charts: array<string, array<string, mixed>>}
     */
    public function build(User $user): array
    {
        $base = $this->scope($user);
        $delayed = $this->delayedAssessments($base);
        $kpis = $this->kpis($user, $base, $delayed);

        return [
            'kpis' => $kpis,
            'charts' => [
                'status' => $this->statusChart($user, $base),
                'delay_by_carrier' => $this->delayByCarrierChart($delayed, $kpis['delay_rate']),
                'exception_type' => $this->exceptionTypeChart($user),
                'trend' => $this->trendChart($base),
            ],
        ];
    }

    // Shipment yang boleh dihitung user ini (customer: miliknya saja)
    private function scope(User $user): Builder
    {
        $query = Shipment::query();
        if ($user->isCustomer()) {
            $query->where('customer_id', $user->id);
        }

        return $query;
    }

    // ---------- KPI ----------

    /**
     * Shipment yang terlambat beserta penilaiannya: yang SEDANG terlambat (belum tiba) dan
     * yang SUDAH tiba tetapi terlambat. Keputusan "terlambat atau tidak" tetap dari
     * EtaService supaya konsisten dengan lencana di daftar shipment.
     *
     * @return array<int, array{shipment: Shipment, assessment: DelayAssessment}>
     */
    private function delayedAssessments(Builder $base): array
    {
        $running = (clone $base)->delayed()->get();
        $deliveredLate = (clone $base)
            ->where('status', Shipment::STATUS_DELIVERED)
            ->whereColumn('delivered_at', '>', 'expected_arrival_at')
            ->get();

        $out = [];
        foreach ($running->concat($deliveredLate) as $shipment) {
            $assessment = $this->eta->assess($shipment);
            if ($assessment->isDelayed()) {
                $out[] = ['shipment' => $shipment, 'assessment' => $assessment];
            }
        }

        return $out;
    }

    /**
     * @param array<int, array{shipment: Shipment, assessment: DelayAssessment}> $delayed
     * @return array<string, mixed>
     */
    private function kpis(User $user, Builder $base, array $delayed): array
    {
        $active = (clone $base)->where('status', '!=', Shipment::STATUS_CANCELLED)->count();
        $delivered = (clone $base)->where('status', Shipment::STATUS_DELIVERED)->count();

        $delayMinutes = array_map(fn ($d) => $d['assessment']->delayMinutes, $delayed);
        $deliveredLateCount = count(array_filter($delayed, fn ($d) => $d['shipment']->status === Shipment::STATUS_DELIVERED));

        $withException = (clone $base)
            ->where('status', '!=', Shipment::STATUS_CANCELLED)
            ->whereHas('exceptions')
            ->count();

        return [
            'active' => $active,
            'delivered' => $delivered,
            'on_time_rate' => $delivered > 0 ? $this->percent($delivered - $deliveredLateCount, $delivered) : null,
            'delay_rate' => $active > 0 ? $this->percent(count($delayed), $active) : null,
            'exception_rate' => $active > 0 ? $this->percent($withException, $active) : null,
            'avg_delay_minutes' => $delayMinutes === [] ? null : (int) round(array_sum($delayMinutes) / count($delayMinutes)),
            'avg_resolution_minutes' => $this->averageResolutionMinutes($user),
        ];
    }

    private function percent(int $part, int $whole): float
    {
        return round($part / $whole * 100, 1);
    }

    // Rata-rata menit dari exception terdeteksi sampai selesai (hanya yang sudah RESOLVED)
    private function averageResolutionMinutes(User $user): ?int
    {
        $rows = $this->exceptions->visibleTo($user)
            ->whereNotNull('resolved_at')
            ->get(['detected_at', 'resolved_at']);

        if ($rows->isEmpty()) {
            return null;
        }

        $total = $rows->sum(fn ($e) => max(0, $e->resolved_at->getTimestamp() - $e->detected_at->getTimestamp()));

        return (int) round($total / $rows->count() / 60);
    }

    // ---------- Grafik ----------

    /**
     * Lima batang berwarna seperti mockup dosen. 'total' = semua shipment (kotak "Total Shipments").
     *
     * @return array<string, mixed>
     */
    private function statusChart(User $user, Builder $base): array
    {
        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $n = fn (string ...$statuses) => (int) collect($statuses)->sum(fn ($s) => $counts[$s] ?? 0);

        $bars = [
            ['Awaiting pickup', $n(Shipment::STATUS_CREATED, Shipment::STATUS_BOOKED), '#f4b740'],
            ['In transit', $n(Shipment::STATUS_PICKED_UP, Shipment::STATUS_IN_TRANSIT), '#2b7fb5'],
            ['Delivered', $n(Shipment::STATUS_DELIVERED), '#1f9d6b'],
            ['Delayed', (clone $base)->delayed()->count(), '#e8892a'],
            ['Open exceptions', $this->exceptions->summary($user)['unresolved'], '#d64545'],
        ];

        return [
            'labels' => array_column($bars, 0),
            'values' => array_map('intval', array_column($bars, 1)),
            'colors' => array_column($bars, 2),
            'total' => (int) $counts->sum(),
        ];
    }

    /**
     * Donut: pembagian shipment terlambat per carrier (persen). 5 carrier teratas, sisanya "Others".
     *
     * @param array<int, array{shipment: Shipment, assessment: DelayAssessment}> $delayed
     * @return array<string, mixed>
     */
    private function delayByCarrierChart(array $delayed, ?float $delayRate): array
    {
        $perCarrier = [];
        foreach ($delayed as $d) {
            $id = $d['shipment']->carrier_id;
            $perCarrier[$id] = ($perCarrier[$id] ?? 0) + 1;
        }
        arsort($perCarrier);

        $names = Carrier::query()->whereIn('id', array_keys($perCarrier))->pluck('name', 'id');

        $labels = [];
        $values = [];
        $others = 0;
        $position = 0;
        foreach ($perCarrier as $carrierId => $count) {
            if ($position < 5) {
                $labels[] = (string) ($names[$carrierId] ?? 'Unknown');
                $values[] = $count;
            } else {
                $others += $count;
            }
            $position++;
        }
        if ($others > 0) {
            $labels[] = 'Others';
            $values[] = $others;
        }

        $total = array_sum($values);
        $percents = array_map(fn ($v) => $total > 0 ? round($v / $total * 100, 1) : 0.0, $values);
        $colors = [];
        foreach ($labels as $i => $label) {
            $colors[] = $label === 'Others' ? self::PALETTE[7] : self::PALETTE[$i % 7];
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'percents' => $percents,
            'colors' => $colors,
            'total' => $total,
            'center' => $delayRate,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function exceptionTypeChart(User $user): array
    {
        $counts = $this->exceptions->visibleTo($user)
            ->selectRaw('type, count(*) as total')->groupBy('type')->orderByDesc('total')->pluck('total', 'type');

        $labels = [];
        $values = [];
        foreach ($counts as $type => $total) {
            $labels[] = ShipmentException::typeLabel($type);
            $values[] = (int) $total;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array<string, mixed>
     */
    private function trendChart(Builder $base): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(5);

        // Dikelompokkan di PHP (bukan SQL) supaya sama di MySQL dan SQLite
        $perMonth = (clone $base)->where('created_at', '>=', $start)->pluck('created_at')
            ->countBy(fn ($date) => $date->format('Y-m'));

        $labels = [];
        $values = [];
        for ($i = 0; $i < 6; $i++) {
            $month = $start->copy()->addMonths($i);
            $labels[] = $month->format('M Y');
            $values[] = (int) ($perMonth[$month->format('Y-m')] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
