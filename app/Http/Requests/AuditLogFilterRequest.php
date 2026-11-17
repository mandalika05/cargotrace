<?php
/*
 * ============================================================
 * BUAT APA : Validasi filter halaman Audit log (web) dan GET /api/v1/audit-logs.
 * FILTER   : entity, action_type, user_id, date_from, date_to, q (cari label).
 * HASIL    : web -> kembali dengan pesan error; API -> 422 JSON.
 * AMAN     : pilihan dibatasi daftar putih; izin admin dicek oleh middleware role:admin.
 * ============================================================
 */

namespace App\Http\Requests;

use App\Models\AuditLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // dibatasi middleware role:admin di route
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $dateTo = ['nullable', 'date_format:Y-m-d'];
        if ($this->filled('date_from')) {
            $dateTo[] = 'after_or_equal:date_from';
        }

        return [
            'entity' => ['nullable', Rule::in(AuditLog::ENTITIES)],
            'action_type' => ['nullable', Rule::in(AuditLog::ACTION_TYPES)],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => $dateTo,
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }
}
