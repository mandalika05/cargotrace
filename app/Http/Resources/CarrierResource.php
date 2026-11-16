<?php
/*
 * ============================================================
 * BUAT APA : Bentuk JSON carrier dan lokasi untuk REST API.
 * ============================================================
 */

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarrierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'code' => $this->code, 'is_active' => (bool) $this->is_active];
    }
}
