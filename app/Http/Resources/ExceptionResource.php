<?php
/*
 * ============================================================
 * BUAT APA : Bentuk JSON satu exception (gangguan shipment).
 * ============================================================
 */

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_id' => $this->shipment_id,
            'shipment_number' => $this->whenLoaded('shipment', fn () => $this->shipment?->shipment_number),
            'type' => $this->type,
            'type_label' => $this->label(),
            'severity' => $this->severity,
            'status' => $this->status,
            'description' => $this->description,
            'detected_at' => $this->detected_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolution_note' => $this->resolution_note,
            'reported_by' => $this->whenLoaded('reporter', fn () => $this->reporter?->name),
            'resolved_by' => $this->whenLoaded('resolver', fn () => $this->resolver?->name),
        ];
    }
}
