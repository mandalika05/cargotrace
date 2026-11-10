{{--
 ============================================================
 BUAT APA : Formulir shipment, dipakai untuk BUAT BARU dan UBAH
            (dibedakan oleh $shipment->exists).
 ITEM BARANG: daftar yang bisa ditambah/dihapus dengan JavaScript sederhana
            (nama field: items[0][description], items[0][quantity], ...).
            Baris baru dibuat dari <template>, indeksnya naik terus (data-next)
            supaya tidak bentrok.
 old()    : mengisi ulang isian kalau validasi gagal.
 RESPONSIF: kolom form 1 lajur di HP, 2 lajur di layar >= sm.
 ============================================================
--}}
@extends('layouts.app')
@section('title', $shipment->exists ? 'Edit '.$shipment->shipment_number : 'New shipment')

@section('actions')
    <a href="{{ $shipment->exists ? route('shipments.show', $shipment) : route('shipments.index') }}" class="btn-ghost">Cancel</a>
@endsection

@section('content')
@php
    $items = old('items', $shipment->exists
        ? $shipment->items->map(fn ($i) => ['description' => $i->description, 'quantity' => $i->quantity, 'weight_kg' => $i->weight_kg])->all()
        : [['description' => '', 'quantity' => 1, 'weight_kg' => '']]);
    $nextIndex = count($items) ? max(array_keys($items)) + 1 : 0;
    $dt = fn ($field) => old($field, $shipment->{$field}?->format('Y-m-d\TH:i'));
@endphp

<form method="POST" action="{{ $shipment->exists ? route('shipments.update', $shipment) : route('shipments.store') }}" class="card form-card">
    @csrf
    @if ($shipment->exists) @method('PUT') @endif

    <div class="form-grid">
        <div class="field-full">
            <label for="customer_id" class="field-label">Customer</label>
            <select id="customer_id" name="customer_id" class="field-input" required>
                <option value="">Select a customer</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string) old('customer_id', $shipment->customer_id) === (string) $customer->id)>{{ $customer->name }} ({{ $customer->email }})</option>
                @endforeach
            </select>
            @error('customer_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="origin_id" class="field-label">Origin</label>
            <select id="origin_id" name="origin_id" class="field-input" required>
                <option value="">Select origin</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}" @selected((string) old('origin_id', $shipment->origin_id) === (string) $location->id)>{{ $location->label() }}</option>
                @endforeach
            </select>
            @error('origin_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="destination_id" class="field-label">Destination</label>
            <select id="destination_id" name="destination_id" class="field-input" required>
                <option value="">Select destination</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}" @selected((string) old('destination_id', $shipment->destination_id) === (string) $location->id)>{{ $location->label() }}</option>
                @endforeach
            </select>
            @error('destination_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="carrier_id" class="field-label">Carrier</label>
            <select id="carrier_id" name="carrier_id" class="field-input" required>
                <option value="">Select a carrier</option>
                @foreach ($carriers as $carrier)
                    <option value="{{ $carrier->id }}" @selected((string) old('carrier_id', $shipment->carrier_id) === (string) $carrier->id)>{{ $carrier->name }} ({{ $carrier->code }})</option>
                @endforeach
                {{-- Saat edit: carrier lama yang sudah nonaktif tetap bisa dipilih --}}
                @if ($shipment->exists && $shipment->carrier && ! $shipment->carrier->is_active)
                    <option value="{{ $shipment->carrier_id }}" @selected((string) old('carrier_id', $shipment->carrier_id) === (string) $shipment->carrier_id)>{{ $shipment->carrier->name }} (inactive)</option>
                @endif
            </select>
            @error('carrier_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="cargo_type" class="field-label">Cargo type</label>
            <input id="cargo_type" name="cargo_type" type="text" list="cargo-types" value="{{ old('cargo_type', $shipment->cargo_type) }}" class="field-input" required maxlength="100" placeholder="e.g. Electronics">
            <datalist id="cargo-types">
                @foreach (['Electronics', 'Textiles', 'Machinery parts', 'Furniture', 'Coffee beans', 'Rubber products', 'Palm oil derivatives', 'Chemicals (non-hazardous)'] as $type)
                    <option value="{{ $type }}"></option>
                @endforeach
            </datalist>
            @error('cargo_type') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="weight_kg" class="field-label">Total weight (kg)</label>
            <input id="weight_kg" name="weight_kg" type="number" step="0.01" min="0.01" inputmode="decimal" value="{{ old('weight_kg', $shipment->weight_kg) }}" class="field-input" required>
            @error('weight_kg') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="volume_cbm" class="field-label">Volume (m&sup3;)</label>
            <input id="volume_cbm" name="volume_cbm" type="number" step="0.01" min="0.01" inputmode="decimal" value="{{ old('volume_cbm', $shipment->volume_cbm) }}" class="field-input" required>
            @error('volume_cbm') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="expected_departure_at" class="field-label">Expected departure</label>
            <input id="expected_departure_at" name="expected_departure_at" type="datetime-local" value="{{ $dt('expected_departure_at') }}" class="field-input" required>
            @error('expected_departure_at') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="expected_arrival_at" class="field-label">Expected arrival</label>
            <input id="expected_arrival_at" name="expected_arrival_at" type="datetime-local" value="{{ $dt('expected_arrival_at') }}" class="field-input" required>
            @error('expected_arrival_at') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="field-full">
            <label for="estimated_arrival_at" class="field-label">Estimated arrival (ETA)</label>
            <input id="estimated_arrival_at" name="estimated_arrival_at" type="datetime-local" value="{{ $dt('estimated_arrival_at') }}" class="field-input">
            <p class="form-hint">Optional. Leave blank to use the expected arrival.</p>
            @error('estimated_arrival_at') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </div>

    {{-- ===== Item barang ===== --}}
    <h2 class="section-title">Cargo items</h2>
    @error('items') <p class="field-error">{{ $message }}</p> @enderror

    <div id="items-list" class="items-list" data-next="{{ $nextIndex }}">
        @foreach ($items as $index => $item)
            <div class="item-row">
                <div class="item-row-grid">
                    <div>
                        <label class="field-label">Description</label>
                        <input name="items[{{ $index }}][description]" type="text" value="{{ $item['description'] ?? '' }}" class="field-input" maxlength="255" required>
                        @error("items.$index.description") <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label">Qty</label>
                        <input name="items[{{ $index }}][quantity]" type="number" min="1" step="1" value="{{ $item['quantity'] ?? 1 }}" class="field-input" required>
                        @error("items.$index.quantity") <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label">Weight (kg)</label>
                        <input name="items[{{ $index }}][weight_kg]" type="number" min="0" step="0.01" value="{{ $item['weight_kg'] ?? '' }}" class="field-input">
                        @error("items.$index.weight_kg") <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <button type="button" class="link-remove" data-remove-item>Remove</button>
                </div>
            </div>
        @endforeach
    </div>

    <div class="form-actions">
        <button type="button" id="add-item" class="btn-ghost"><x-icon name="plus" /> Add item</button>
    </div>

    {{-- Cetakan baris baru. __INDEX__ diganti angka oleh JavaScript. --}}
    <template id="item-template">
        <div class="item-row">
            <div class="item-row-grid">
                <div><label class="field-label">Description</label><input name="items[__INDEX__][description]" type="text" class="field-input" maxlength="255" required></div>
                <div><label class="field-label">Qty</label><input name="items[__INDEX__][quantity]" type="number" min="1" step="1" value="1" class="field-input" required></div>
                <div><label class="field-label">Weight (kg)</label><input name="items[__INDEX__][weight_kg]" type="number" min="0" step="0.01" class="field-input"></div>
                <button type="button" class="link-remove" data-remove-item>Remove</button>
            </div>
        </div>
    </template>

    <div class="form-actions">
        <button type="submit" class="btn-primary">{{ $shipment->exists ? 'Save changes' : 'Create shipment' }}</button>
        <a href="{{ $shipment->exists ? route('shipments.show', $shipment) : route('shipments.index') }}" class="btn-ghost">Cancel</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
    // Tambah / hapus baris item barang
    (function () {
        var list = document.getElementById('items-list');
        var template = document.getElementById('item-template');
        var next = parseInt(list.dataset.next, 10) || 0;

        document.getElementById('add-item').addEventListener('click', function () {
            list.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, next++));
        });

        list.addEventListener('click', function (event) {
            var button = event.target.closest('[data-remove-item]');
            if (!button) return;
            // Minimal 1 baris harus tersisa
            if (list.querySelectorAll('.item-row').length > 1) {
                button.closest('.item-row').remove();
            }
        });
    })();
</script>
@endpush
