<?php
/*
 * ============================================================
 * BUAT APA : Validasi form "Update ETA" (staf, shipment belum terminal).
 * authorize(): ShipmentPolicy::update, jadi customer -> 403.
 * ============================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEtaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('shipment'));
    }

    public function rules(): array
    {
        return [
            'estimated_arrival_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
        ];
    }
}
