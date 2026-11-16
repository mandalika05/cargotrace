<?php
/*
 * ============================================================
 * BUAT APA : Bentuk JSON shipment (dipakai daftar dan detail). Isinya sama dengan yang dilihat di web.
 * ============================================================
 */

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_number' => $this->shipment_number,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'progress_percent' => $this->progressPercent(),
            'customer' => $this->whenLoaded('customer', fn () => ['id' => $this->customer->id, 'name' => $this->customer->name]),
            'carrier' => new CarrierResource($this->whenLoaded('carrier')),
            'origin' => new LocationResource($this->whenLoaded('origin')),
            'destination' => new LocationResource($this->whenLoaded('destination')),
            'cargo_type' => $this->cargo_type,
            'weight_kg' => (float) $this->weight_kg,
            'volume_cbm' => (float) $this->volume_cbm,
            'expected_departure_at' => $this->expected_departure_at?->toIso8601String(),
            'expected_arrival_at' => $this->expected_arrival_at?->toIso8601String(),
            'estimated_arrival_at' => $this->estimated_arrival_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'delay' => new DelayResource($this->delay()),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'description' => $i->description,
                'quantity' => (int) $i->quantity,
                'weight_kg' => $i->weight_kg !== null ? (float) $i->weight_kg : null,
            ])->values()),
            'unresolved_exceptions' => $this->whenCounted('unresolvedExceptions'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
