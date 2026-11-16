<?php
/*
 * ============================================================
 * BUAT APA : Bentuk JSON satu event tracking (timeline).
 * ============================================================
 */

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackingEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'label' => $this->label(),
            'is_marker' => $this->isMarker(), // penanda exception / ETA, bukan perjalanan fisik
            'location_name' => $this->location_name,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'description' => $this->description,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
        ];
    }
}
