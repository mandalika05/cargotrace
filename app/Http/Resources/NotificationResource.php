<?php
/*
 * ============================================================
 * BUAT APA : Bentuk JSON satu notifikasi in-app.
 * ============================================================
 */

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = (array) $this->data;

        return [
            'id' => $this->id,
            'kind' => $data['kind'] ?? null,
            'level' => $data['level'] ?? 'info',
            'title' => $data['title'] ?? '',
            'message' => $data['message'] ?? '',
            'url' => $data['url'] ?? null,
            'shipment_number' => $data['shipment_number'] ?? null,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
