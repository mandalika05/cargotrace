<?php
/*
 * ============================================================
 * BUAT APA : Validasi form carrier (buat & ubah). Hanya Admin
 *            (dijaga middleware role:admin di route).
 * Rule::unique(...)->ignore(): saat mengubah, kode miliknya sendiri tidak dianggap bentrok.
 * ============================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CarrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'alpha_num', 'max:10', Rule::unique('carriers', 'code')->ignore($this->route('carrier'))],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    // Kode selalu huruf besar
    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => strtoupper($this->input('code'))]);
        }
    }
}
