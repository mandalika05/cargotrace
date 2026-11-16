<?php
/*
 * ============================================================
 * BUAT APA : Validasi perubahan status exception (INVESTIGATING / RESOLVED).
 * authorize(): ShipmentExceptionPolicy::update (staf, dan exception belum RESOLVED).
 * Catatan wajib saat RESOLVED dijaga ExceptionService.
 * ============================================================
 */

namespace App\Http\Requests;

use App\Models\ShipmentException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExceptionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('exception'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([ShipmentException::STATUS_INVESTIGATING, ShipmentException::STATUS_RESOLVED])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
