{{--
 ============================================================
 BUAT APA : Formulir carrier untuk BUAT dan UBAH ($carrier->exists membedakan).
 is_active: <input hidden value=0> + checkbox value=1, supaya kotak yang tidak
            dicentang tetap mengirim nilai 0.
 ============================================================
--}}
@extends('layouts.app')
@section('title', $carrier->exists ? 'Edit carrier' : 'New carrier')

@section('actions')
    <a href="{{ route('carriers.index') }}" class="btn-ghost">Cancel</a>
@endsection

@section('content')
<form method="POST" action="{{ $carrier->exists ? route('carriers.update', $carrier) : route('carriers.store') }}" class="card form-card">
    @csrf
    @if ($carrier->exists) @method('PUT') @endif

    <div class="form-grid">
        <div class="field-full">
            <label for="name" class="field-label">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $carrier->name) }}" class="field-input" required maxlength="150">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="code" class="field-label">Code</label>
            <input id="code" name="code" type="text" value="{{ old('code', $carrier->code) }}" class="field-input" required maxlength="10" placeholder="e.g. NFL">
            <p class="form-hint">Letters and numbers only. Must be unique.</p>
            @error('code') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="contact_email" class="field-label">Contact email</label>
            <input id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', $carrier->contact_email) }}" class="field-input">
            @error('contact_email') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="field-full">
            <input type="hidden" name="is_active" value="0">
            <label class="check-line">
                <input type="checkbox" name="is_active" value="1" class="check-box" @checked(old('is_active', $carrier->is_active ? '1' : '0') == '1')>
                Active (can be chosen for new shipments)
            </label>
            @error('is_active') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-primary">{{ $carrier->exists ? 'Save changes' : 'Create carrier' }}</button>
        <a href="{{ route('carriers.index') }}" class="btn-ghost">Cancel</a>
    </div>
</form>
@endsection
