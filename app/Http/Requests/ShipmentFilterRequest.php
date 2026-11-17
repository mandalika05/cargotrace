<?php
/*
 * ============================================================
 * BUAT APA : Validasi filter daftar shipment (P13.2). Dipakai SAMA oleh halaman web
 *            (/shipments), REST API (/api/v1/shipments), dan export CSV, jadi aturannya
 *            konsisten di ketiganya.
 * FILTER   : q, status, carrier_id, origin_id, destination_id, delay, date_field,
 *            date_from, date_to, sort.
 * HASIL    : web -> kembali ke halaman dengan pesan error; API -> 422 JSON.
 * AMAN     : semua pilihan dibatasi daftar putih (tidak ada nama kolom dari pengguna).
 * ============================================================
 */

namespace App\Http\Requests;

use App\Models\Shipment;
use App\Services\ShipmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShipmentFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin 'viewAny' dicek di controller; customer dibatasi di ShipmentService
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $dateTo = ['nullable', 'date_format:Y-m-d'];
        if ($this->filled('date_from')) {
            $dateTo[] = 'after_or_equal:date_from'; // hanya dicek bila tanggal awal diisi
        }

        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Shipment::STATUSES)],
            'carrier_id' => ['nullable', 'integer', 'min:1'],
            'origin_id' => ['nullable', 'integer', 'exists:locations,id'],
            'destination_id' => ['nullable', 'integer', 'exists:locations,id'],
            'delay' => ['nullable', Rule::in(ShipmentService::DELAY_FILTERS)],
            'date_field' => ['nullable', Rule::in(ShipmentService::DATE_FIELDS)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => $dateTo,
            'sort' => ['nullable', Rule::in(ShipmentService::SORTS)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'date_from.date_format' => 'The start date must look like 2026-12-31.',
            'date_to.date_format' => 'The end date must look like 2026-12-31.',
            'date_to.after_or_equal' => 'The end date must be the same as or after the start date.',
            'origin_id.exists' => 'That origin does not exist.',
            'destination_id.exists' => 'That destination does not exist.',
        ];
    }
}
