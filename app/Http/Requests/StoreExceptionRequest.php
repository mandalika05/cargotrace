<?php
/*
 * ============================================================
 * BUAT APA : Validasi form "Report exception" di halaman detail shipment.
 * authorize(): hanya staf (ShipmentExceptionPolicy::create). Customer -> 403.
 * Aturan yang butuh data (shipment dibatalkan, dst) dijaga ExceptionService.
 * ============================================================
 */

namespace App\Http\Requests;

use App\Models\ShipmentException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ShipmentException::class);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(ShipmentException::TYPES)],
            'severity' => ['required', Rule::in(ShipmentException::SEVERITIES)],
            'description' => ['nullable', 'string', 'max:1000'],
            'detected_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    public function messages(): array
    {
        return ['detected_at.before_or_equal' => 'Detection time cannot be in the future.'];
    }
}
