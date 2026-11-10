<?php
/*
 * ============================================================
 * BUAT APA : Validasi form kelola user oleh Admin (buat & ubah).
 * role     : divalidasi hanya boleh salah satu dari 3 peran.
 * password : wajib saat membuat; saat mengubah boleh kosong (= tidak diganti).
 * ============================================================
 */

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $editing = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($editing)],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_OPERATOR, User::ROLE_CUSTOMER])],
            'password' => [$editing ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ];
    }
}
