<?php
/*
 * ============================================================
 * BUAT APA : ATURAN ETA dan DELAY (slide 8 brief). Deterministik, tanpa AI/ML,
 *            sehingga bisa diuji dan dijelaskan saat interview.
 *
 * ATURAN   :
 *   1. Delayed  = ETA lebih lambat dari expected arrival (jadwal tiba).
 *   2. Lama     = ETA - expected arrival.
 *   3. Tingkat  = 0 - low_max_hours      -> LOW
 *                 > low_max - medium_max -> MEDIUM
 *                 > medium_max_hours     -> HIGH
 *                 (ambang dari tabel settings, bawaan di config/cargotrace.php)
 *   4. Shipment yang SUDAH DELIVERED dinilai dari waktu tiba sebenarnya.
 *   5. Shipment yang BELUM tiba tetapi jadwal tibanya sudah lewat dan ETA belum
 *      diperbarui dianggap terlambat sejak jadwal itu ("overdue").
 *   6. Shipment CANCELLED: aturan delay tidak berlaku.
 *
 * FUNGSI   :
 *   thresholds()  ambang yang sedang dipakai
 *   classify()    menit keterlambatan -> LOW/MEDIUM/HIGH (fungsi murni, mudah diuji)
 *   assess()      penilaian lengkap + alasan (DelayAssessment)
 *   updateEta()   operator memperbarui ETA + alasan; tercatat di timeline
 * ============================================================
 */

namespace App\Services;

use App\Models\Setting;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EtaService
{
    /** @return array{low_max_hours:int, medium_max_hours:int} */
    public function thresholds(): array
    {
        $low = (int) Setting::getValue('delay_low_max_hours', config('cargotrace.delay.low_max_hours'));
        $medium = (int) Setting::getValue('delay_medium_max_hours', config('cargotrace.delay.medium_max_hours'));

        // Pengaman: ambang tidak masuk akal -> pakai bawaan
        if ($low < 1 || $medium <= $low) {
            $low = (int) config('cargotrace.delay.low_max_hours');
            $medium = (int) config('cargotrace.delay.medium_max_hours');
        }

        return ['low_max_hours' => $low, 'medium_max_hours' => $medium];
    }

    /**
     * Menit keterlambatan -> tingkat. 0 atau negatif = tidak terlambat (null).
     * Batas bawah: tepat 6 jam masih LOW, 6 jam 1 menit sudah MEDIUM ("> 6 jam").
     *
     * @param array{low_max_hours:int, medium_max_hours:int}|null $thresholds
     */
    public function classify(int $delayMinutes, ?array $thresholds = null): ?string
    {
        if ($delayMinutes <= 0) {
            return null;
        }

        $t = $thresholds ?? $this->thresholds();

        if ($delayMinutes <= $t['low_max_hours'] * 60) {
            return ShipmentException::SEVERITY_LOW;
        }
        if ($delayMinutes <= $t['medium_max_hours'] * 60) {
            return ShipmentException::SEVERITY_MEDIUM;
        }

        return ShipmentException::SEVERITY_HIGH;
    }

    // PENILAIAN LENGKAP satu shipment: terlambat atau tidak, berapa menit, LOW/MEDIUM/HIGH, dan ALASANNYA.
    // Langkah: (1) CANCELLED -> aturan delay tidak berlaku; (2) tentukan waktu tiba efektif
    // (DELIVERED = waktu tiba sebenarnya; belum tiba = ETA, atau sekarang kalau jadwal sudah lewat);
    // (3) selisih dengan expected arrival -> menit; (4) menit -> tingkat lewat classify().
    // $now boleh diisi dari luar agar mudah diuji; kalau kosong dipakai waktu sekarang.
    public function assess(Shipment $shipment, ?CarbonInterface $now = null): DelayAssessment
    {
        $now ??= now();
        $t = $this->thresholds();
        $expected = $shipment->expected_arrival_at;

        if ($shipment->status === Shipment::STATUS_CANCELLED || $expected === null) {
            return new DelayAssessment(
                DelayAssessment::STATE_NOT_APPLICABLE, 0, null,
                'The shipment is cancelled, so the delay rules do not apply.',
                false, $expected, null, $t
            );
        }

        $delivered = $shipment->status === Shipment::STATUS_DELIVERED;
        $overdue = false;

        if ($delivered) {
            $effective = $shipment->delivered_at ?? $shipment->estimated_arrival_at ?? $expected;
        } else {
            $effective = $shipment->estimated_arrival_at ?? $expected;
            // Jadwal sudah lewat, shipment belum tiba, ETA belum digeser -> terlambat sejak jadwal
            if ($effective->lte($expected) && $now->gt($expected)) {
                $effective = $now;
                $overdue = true;
            }
        }

        $seconds = $effective->getTimestamp() - $expected->getTimestamp();
        $minutes = $seconds > 0 ? (int) ceil($seconds / 60) : (int) floor($seconds / 60);
        $severity = $this->classify($minutes, $t);
        $state = $severity === null ? DelayAssessment::STATE_ON_TIME : DelayAssessment::STATE_DELAYED;

        return new DelayAssessment(
            $state, $minutes, $severity,
            $this->reason($state, $minutes, $severity, $delivered, $overdue, $effective, $expected, $t),
            $overdue, $expected, $effective, $t
        );
    }

    /**
     * Operator memperbarui ETA. Wajib ada alasan; tercatat sebagai event ETA_UPDATED
     * di timeline (riwayat lama tidak ditimpa). Mengembalikan event yang dibuat.
     */
    public function updateEta(Shipment $shipment, CarbonInterface|string $newEta, string $reason, User $actor): TrackingEvent
    {
        if ($actor->isCustomer()) {
            throw new AuthorizationException('Only staff can update the ETA.');
        }

        return DB::transaction(function () use ($shipment, $newEta, $reason, $actor) {
            $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if ($shipment->isTerminal()) {
                $this->fail('estimated_arrival_at', 'The ETA of a delivered or cancelled shipment cannot be changed.');
            }

            $eta = Carbon::parse($newEta);
            if ($eta->lte($shipment->expected_departure_at)) {
                $this->fail('estimated_arrival_at', 'The ETA must be after the expected departure.');
            }

            $reason = trim($reason);
            if (mb_strlen($reason) < 5) {
                $this->fail('reason', 'Please give a reason for the new ETA (at least 5 characters).');
            }

            $old = $shipment->estimated_arrival_at;
            if ($old && $old->equalTo($eta)) {
                $this->fail('estimated_arrival_at', 'The new ETA is the same as the current one.');
            }

            $shipment->forceFill(['estimated_arrival_at' => $eta])->save();
            $assessment = $this->assess($shipment);

            $text = 'ETA changed from '.($old?->format('d M Y, H:i') ?? 'not set').' to '.$eta->format('d M Y, H:i')
                .'. Reason: '.$reason.'. '
                .($assessment->isDelayed()
                    ? 'Now delayed by '.$assessment->duration().' ('.$assessment->severity.').'
                    : 'Shipment is on time.');

            $event = new TrackingEvent([
                'event_type' => TrackingEvent::TYPE_ETA_UPDATED,
                'occurred_at' => now(),
                'description' => $text,
            ]);
            $event->forceFill(['shipment_id' => $shipment->id, 'created_by' => $actor->id])->save();

            return $event;
        });
    }

    // ---------- Pembantu ----------

    /** @param array{low_max_hours:int, medium_max_hours:int} $t */
    private function reason(string $state, int $minutes, ?string $severity, bool $delivered, bool $overdue, CarbonInterface $effective, CarbonInterface $expected, array $t): string
    {
        $fmt = fn (CarbonInterface $d) => $d->format('d M Y, H:i');
        $rule = "Rule: up to {$t['low_max_hours']}h = LOW, up to {$t['medium_max_hours']}h = MEDIUM, above {$t['medium_max_hours']}h = HIGH.";

        if ($state === DelayAssessment::STATE_DELAYED) {
            $length = DelayAssessment::formatMinutes($minutes);

            if ($overdue) {
                return "The expected arrival ({$fmt($expected)}) has passed and the shipment has not arrived. "
                    ."It is now {$length} late ({$severity}). {$rule}";
            }

            $subject = $delivered ? 'Delivery time' : 'ETA';

            return "{$subject} {$fmt($effective)} is {$length} after the expected arrival ({$fmt($expected)}): {$severity}. {$rule}";
        }

        $subject = $delivered ? 'Delivery time' : 'ETA';
        if ($minutes < 0) {
            $early = DelayAssessment::formatMinutes(abs($minutes));

            return "{$subject} {$fmt($effective)} is {$early} before the expected arrival ({$fmt($expected)}). On time.";
        }

        return "{$subject} {$fmt($effective)} matches the expected arrival ({$fmt($expected)}). On time.";
    }

    // Melempar error validasi untuk satu kolom (web: kembali ke form dengan pesan; API: respons 422).
    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
