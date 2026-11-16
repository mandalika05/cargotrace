<?php
/*
 * ============================================================
 * BUAT APA : "Hasil penilaian delay" satu shipment, dikembalikan EtaService::assess().
 *            Isinya SEMUA yang diminta slide 8: apakah terlambat, berapa lama,
 *            tingkat LOW/MEDIUM/HIGH, ALASAN dalam kalimat, dan nilai yang dipakai.
 * ISI      : state   ON_TIME | DELAYED | NOT_APPLICABLE (shipment dibatalkan)
 *            delayMinutes  positif = terlambat, negatif = lebih cepat dari jadwal
 *            severity      LOW | MEDIUM | HIGH | null
 *            overdue       true bila jadwal tiba sudah lewat tapi shipment belum tiba
 *                          dan ETA belum diperbarui
 * ============================================================
 */

namespace App\Services;

use Carbon\CarbonInterface;

final class DelayAssessment
{
    public const STATE_ON_TIME = 'ON_TIME';
    public const STATE_DELAYED = 'DELAYED';
    public const STATE_NOT_APPLICABLE = 'NOT_APPLICABLE';

    /** @param array{low_max_hours:int, medium_max_hours:int} $thresholds */
    public function __construct(
        public readonly string $state,
        public readonly int $delayMinutes,
        public readonly ?string $severity,
        public readonly string $reason,
        public readonly bool $overdue,
        public readonly ?CarbonInterface $expectedAt,
        public readonly ?CarbonInterface $effectiveAt,
        public readonly array $thresholds,
    ) {
    }

    // Apakah shipment ini terlambat? (true hanya kalau state = DELAYED)
    public function isDelayed(): bool
    {
        return $this->state === self::STATE_DELAYED;
    }

    // Teks lencana: "Delayed - High", "On time", "Not applicable"
    public function label(): string
    {
        return match ($this->state) {
            self::STATE_DELAYED => 'Delayed - '.ucfirst(strtolower((string) $this->severity)),
            self::STATE_ON_TIME => 'On time',
            default => 'Not applicable',
        };
    }

    // Kelas chip (polish.css): HIGH merah, LOW/MEDIUM oranye, tepat waktu hijau
    public function chipClass(): string
    {
        return match (true) {
            $this->state === self::STATE_NOT_APPLICABLE => 'chip-cancelled',
            $this->state === self::STATE_ON_TIME => 'chip-delivered',
            $this->severity === 'HIGH' => 'chip-exception',
            default => 'chip-delay',
        };
    }

    // Lama keterlambatan yang enak dibaca: "5h 30m", "1d 6h", "45m"
    public function duration(): string
    {
        return self::formatMinutes(abs($this->delayMinutes));
    }

    // Mengubah jumlah menit jadi teks singkat: 45 -> "45m", 330 -> "5h 30m", 1800 -> "1d 6h".
    public static function formatMinutes(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;

        if ($days > 0) {
            return $days.'d '.$hours.'h';
        }
        if ($hours > 0) {
            return $hours.'h '.str_pad((string) $mins, 2, '0', STR_PAD_LEFT).'m';
        }

        return $mins.'m';
    }
}
