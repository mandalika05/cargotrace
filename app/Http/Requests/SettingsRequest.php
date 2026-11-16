<?php
/*
 * ============================================================
 * BUAT APA : Validasi halaman Settings (khusus Admin): ambang delay.
 * ATURAN   : LOW harus lebih kecil dari MEDIUM, supaya tiga tingkat selalu masuk akal.
 * ============================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'delay_low_max_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'delay_medium_max_hours' => ['required', 'integer', 'gt:delay_low_max_hours', 'max:720'],
        ];
    }

    public function messages(): array
    {
        return ['delay_medium_max_hours.gt' => 'The MEDIUM limit must be larger than the LOW limit.'];
    }
}
