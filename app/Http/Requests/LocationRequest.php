<?php
/*
 * ============================================================
 * BUAT APA : Validasi form lokasi (buat & ubah). Hanya Admin.
 * KOORDINAT: latitude -90..90, longitude -180..180. Keduanya harus diisi
 *            bersamaan (required_with) atau dua-duanya kosong.
 * ============================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('locations', 'name')
                ->where('city', $this->input('city'))
                ->ignore($this->route('location'))],
            'city' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }
}
