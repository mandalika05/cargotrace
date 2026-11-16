<?php
/*
 * ============================================================
 * BUAT APA : Bentuk JSON hasil aturan delay (alasan + nilai yang dipakai, sesuai slide 8 brief).
 * ============================================================
 */

namespace App\Http\Resources;

use App\Services\DelayAssessment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DelayAssessment */
class DelayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'state' => $this->state,
            'is_delayed' => $this->isDelayed(),
            'severity' => $this->severity,
            'label' => $this->label(),
            'delay_minutes' => $this->delayMinutes,
            'delay_human' => $this->duration(),
            'overdue' => $this->overdue,
            'reason' => $this->reason,
            'expected_arrival_at' => $this->expectedAt?->toIso8601String(),
            'effective_arrival_at' => $this->effectiveAt?->toIso8601String(),
            'thresholds' => $this->thresholds,
        ];
    }
}
