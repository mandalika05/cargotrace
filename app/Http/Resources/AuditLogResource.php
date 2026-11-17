<?php
/*
 * ============================================================
 * BUAT APA : Bentuk JSON satu baris audit log (khusus admin).
 * ============================================================
 */

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'entity_label' => $this->entity_label,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'actor' => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name, 'role' => $this->user->role] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
