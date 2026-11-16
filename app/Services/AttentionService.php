<?php
/*
 * ============================================================
 * BUAT APA : Antrean "NEEDS ATTENTION" (inti ide control tower): shipment yang
 *            butuh tindak lanjut staf, diurutkan dari yang PALING GAWAT.
 *
 * MASUK ANTREAN : shipment belum selesai yang
 *                 (a) sedang terlambat (aturan EtaService), atau
 *                 (b) punya exception yang belum RESOLVED.
 *
 * SKOR (sederhana, bisa dijelaskan):
 *   keterlambatan  LOW 10 | MEDIUM 30 | HIGH 60
 *   tiap exception belum selesai  LOW 10 | MEDIUM 20 | HIGH 40, ditambah 5 bila masih OPEN
 *                  (belum ada yang menangani)
 *   prioritas:  skor >= 70 CRITICAL | >= 30 HIGH | selain itu WATCH
 * Seri skor diurutkan dari keterlambatan terlama.
 * ============================================================
 */

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentException;
use Illuminate\Support\Collection;

class AttentionService
{
    private const DELAY_POINTS = ['LOW' => 10, 'MEDIUM' => 30, 'HIGH' => 60];
    private const EXCEPTION_POINTS = ['LOW' => 10, 'MEDIUM' => 20, 'HIGH' => 40];
    private const OPEN_BONUS = 5;

    public function __construct(private readonly EtaService $eta)
    {
    }

    /**
     * @return Collection<int, array{shipment: Shipment, delay: DelayAssessment, exceptions: Collection, score: int, priority: string, reasons: array<int, string>}>
     */
    public function queue(int $limit = 50): Collection
    {
        $shipments = Shipment::query()
            ->whereNotIn('status', Shipment::TERMINAL_STATUSES)
            ->where(function ($q) {
                $q->whereHas('exceptions', fn ($e) => $e->unresolved())
                  ->orWhere(fn ($d) => $d->delayed());
            })
            ->with(['customer:id,name', 'carrier:id,name', 'origin:id,city', 'destination:id,city', 'unresolvedExceptions'])
            ->get();

        return $shipments
            ->map(fn (Shipment $s) => $this->entry($s))
            ->sortByDesc(fn (array $e) => [$e['score'], $e['delay']->delayMinutes])
            ->take($limit)
            ->values();
    }

    public function entry(Shipment $shipment): array
    {
        $delay = $this->eta->assess($shipment);
        $exceptions = $shipment->unresolvedExceptions;

        $score = $delay->isDelayed() ? self::DELAY_POINTS[$delay->severity] : 0;
        $reasons = [];

        if ($delay->isDelayed()) {
            $reasons[] = ($delay->overdue ? 'Overdue by ' : 'Delayed by ').$delay->duration().' ('.$delay->severity.')';
        }

        foreach ($exceptions as $exception) {
            $score += self::EXCEPTION_POINTS[$exception->severity] ?? 0;
            if ($exception->status === ShipmentException::STATUS_OPEN) {
                $score += self::OPEN_BONUS;
            }
            $reasons[] = $exception->label().' ('.$exception->severity.', '.strtolower($exception->status).')';
        }

        return [
            'shipment' => $shipment,
            'delay' => $delay,
            'exceptions' => $exceptions,
            'score' => $score,
            'priority' => $this->priority($score),
            'reasons' => $reasons,
        ];
    }

    public function priority(int $score): string
    {
        return match (true) {
            $score >= 70 => 'CRITICAL',
            $score >= 30 => 'HIGH',
            default => 'WATCH',
        };
    }
}
