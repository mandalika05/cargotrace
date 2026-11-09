<?php
/*
 * ============================================================
 * BUAT APA : Aturan validasi form login (Form Request).
 *            Validasi dipisah dari controller supaya controller tipis.
 * ============================================================
 */

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // halaman login boleh diakses siapa saja
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
