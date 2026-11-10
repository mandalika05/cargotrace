{{--
 ============================================================
 BUAT APA : Formulir akun untuk BUAT dan UBAH ($account->exists membedakan).
            Saat ubah, password boleh dikosongkan (= tidak diganti).
            Admin tidak bisa mengubah perannya sendiri (dijaga UserController).
 ============================================================
--}}
@extends('layouts.app')
@section('title', $account->exists ? 'Edit user' : 'New user')

@section('actions')
    <a href="{{ route('users.index') }}" class="btn-ghost">Cancel</a>
@endsection

@section('content')
<form method="POST" action="{{ $account->exists ? route('users.update', $account) : route('users.store') }}" class="card form-card">
    @csrf
    @if ($account->exists) @method('PUT') @endif

    <div class="form-grid">
        <div>
            <label for="name" class="field-label">Full name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $account->name) }}" class="field-input" required maxlength="255">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="email" class="field-label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $account->email) }}" class="field-input" required maxlength="255">
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="field-full">
            <label for="role" class="field-label">Role</label>
            <select id="role" name="role" class="field-input" required>
                @foreach (['admin' => 'Administrator', 'operator' => 'Logistics Operator', 'customer' => 'Customer'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('role', $account->role) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('role') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="field-label">{{ $account->exists ? 'New password' : 'Password' }}</label>
            <input id="password" name="password" type="password" class="field-input" autocomplete="new-password" @required(! $account->exists)>
            <p class="form-hint">{{ $account->exists ? 'Leave blank to keep the current password.' : 'At least 8 characters.' }}</p>
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="field-label">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="field-input" autocomplete="new-password">
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-primary">{{ $account->exists ? 'Save changes' : 'Create user' }}</button>
        <a href="{{ route('users.index') }}" class="btn-ghost">Cancel</a>
    </div>
</form>
@endsection
