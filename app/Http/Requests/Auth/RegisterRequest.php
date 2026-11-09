<?php
/*
 * ============================================================
 * BUAT APA : Aturan validasi form pendaftaran customer.
 * PENTING  : 'role' TIDAK ada di aturan ini. validated() hanya mengembalikan
 *            kolom yang ada di rules(), jadi role=admin dari penyerang diabaikan.
 * ============================================================
 */

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            // 'confirmed' = harus sama dengan field password_confirmation
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
