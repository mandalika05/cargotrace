{{--
 ============================================================
 BUAT APA : Formulir lokasi untuk BUAT dan UBAH. Koordinat opsional
            (P12 akan melengkapi lewat geocoding).
 ============================================================
--}}
@extends('layouts.app')
@section('title', $location->exists ? 'Edit location' : 'New location')

@section('actions')
    <a href="{{ route('locations.index') }}" class="btn-ghost">Cancel</a>
@endsection

@section('content')
<form method="POST" action="{{ $location->exists ? route('locations.update', $location) : route('locations.store') }}" class="card form-card">
    @csrf
    @if ($location->exists) @method('PUT') @endif

    <div class="form-grid">
        <div class="field-full">
            <label for="name" class="field-label">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $location->name) }}" class="field-input" required maxlength="150" placeholder="e.g. Rotterdam Port">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="city" class="field-label">City</label>
            <input id="city" name="city" type="text" value="{{ old('city', $location->city) }}" class="field-input" required maxlength="100">
            @error('city') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="country" class="field-label">Country</label>
            <input id="country" name="country" type="text" value="{{ old('country', $location->country) }}" class="field-input" required maxlength="100">
            @error('country') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="latitude" class="field-label">Latitude</label>
            <input id="latitude" name="latitude" type="number" step="0.0000001" min="-90" max="90" value="{{ old('latitude', $location->latitude) }}" class="field-input" placeholder="e.g. 51.95">
            @error('latitude') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="longitude" class="field-label">Longitude</label>
            <input id="longitude" name="longitude" type="number" step="0.0000001" min="-180" max="180" value="{{ old('longitude', $location->longitude) }}" class="field-input" placeholder="e.g. 4.14">
            @error('longitude') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <p class="form-hint field-full">Coordinates are optional now. They are used to draw the map.</p>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-primary">{{ $location->exists ? 'Save changes' : 'Create location' }}</button>
        <a href="{{ route('locations.index') }}" class="btn-ghost">Cancel</a>
    </div>
</form>
@endsection
