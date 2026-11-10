<?php
/*
 * ============================================================
 * BUAT APA : Validasi form tambah tracking event.
 * authorize(): memakai ShipmentPolicy::update (staf + shipment belum terminal),
 *            jadi customer ditolak 403.
 * Aturan yang butuh data di database (urutan waktu, transisi status) dijaga
 * TrackingService, bukan di sini.
 * ============================================================
 */

namespace App\Http\Requests;

use App\Models\Shipment;
use App\Models\TrackingEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TrackingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('shipment'));
    }

    public function rules(): array
    {
        // CREATED bukan event: itu keadaan awal shipment
        $types = array_merge(
            array_values(array_diff(Shipment::STATUSES, [Shipment::STATUS_CREATED])),
            [TrackingEvent::TYPE_LOCATION_UPDATE]
        );

        return [
            'event_type' => ['required', Rule::in($types)],
            'location_id' => ['nullable', 'exists:locations,id'],
            'location_name' => ['nullable', 'string', 'max:150'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['occurred_at.before_or_equal' => 'Event time cannot be in the future.'];
    }

    // LOCATION_UPDATE tanpa informasi lokasi tidak ada artinya
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($this->input('event_type') === TrackingEvent::TYPE_LOCATION_UPDATE
                && ! $this->filled('location_id') && ! $this->filled('location_name') && ! $this->filled('latitude')) {
                $v->errors()->add('location_name', 'A location update needs a location, a place name or coordinates.');
            }
        });
    }
}
