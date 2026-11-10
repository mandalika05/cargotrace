<?php
/*
 * ============================================================
 * BUAT APA : Validasi form shipment (dipakai untuk BUAT dan UBAH).
 * authorize(): izin dicek di sini lewat Policy -> kalau tidak boleh, otomatis 403.
 * ATURAN PENTING:
 *   - customer_id harus user ber-role customer (bukan sembarang user).
 *   - origin tidak boleh sama dengan destination.
 *   - tiba harus SETELAH berangkat.
 *   - minimal 1 item barang.
 * status TIDAK ada di sini: status tidak bisa diatur dari form.
 * ============================================================
 */

namespace App\Http\Requests;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class ShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shipment = $this->route('shipment');

        return $shipment instanceof Shipment
            ? $this->user()->can('update', $shipment)
            : $this->user()->can('create', Shipment::class);
    }

    public function rules(): array
    {
        $currentCarrier = $this->route('shipment')?->carrier_id;

        return [
            'customer_id' => ['required', Rule::exists('users', 'id')->where('role', User::ROLE_CUSTOMER)],
            // Carrier harus aktif (kecuali carrier yang sudah dipakai shipment ini)
            'carrier_id' => ['required', Rule::exists('carriers', 'id')->where(function ($q) use ($currentCarrier) {
                $q->where(function ($q2) use ($currentCarrier) {
                    $q2->where('is_active', true);
                    if ($currentCarrier) {
                        $q2->orWhere('id', $currentCarrier);
                    }
                });
            })],
            'origin_id' => ['required', 'exists:locations,id', 'different:destination_id'],
            'destination_id' => ['required', 'exists:locations,id'],
            'cargo_type' => ['required', 'string', 'max:100'],
            'weight_kg' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'volume_cbm' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'expected_departure_at' => ['required', 'date'],
            'expected_arrival_at' => ['required', 'date', 'after:expected_departure_at'],
            'estimated_arrival_at' => ['nullable', 'date'],

            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'origin_id.different' => 'Origin and destination must be different locations.',
            'expected_arrival_at.after' => 'Expected arrival must be after expected departure.',
            'items.required' => 'Add at least one cargo item.',
            'items.min' => 'Add at least one cargo item.',
        ];
    }

    // Data shipment tanpa daftar item
    public function shipmentData(): array
    {
        return Arr::except($this->validated(), ['items']);
    }

    public function items(): array
    {
        return $this->validated()['items'];
    }
}
