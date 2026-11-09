{{--
 ============================================================
 BUAT APA : Halaman daftar akun CUSTOMER. Tidak ada pilihan role di form ini:
            role selalu customer (diatur di server, bukan dari input pengguna).
 ============================================================
--}}
@extends('layouts.guest')
@section('title', 'Create account')

@section('content')
    <h2 class="form-title">Create your account</h2>
    <p class="form-sub">Track your shipments in one place.</p>

    <form method="POST" action="{{ url('/register') }}" class="form-stack">
        @csrf
        <div>
            <label for="name" class="field-label">Full name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" class="field-input">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="field-label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" class="field-input">
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="field-label">Password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="field-input">
            <p class="form-hint">At least 8 characters.</p>
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="field-label">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="field-input">
        </div>

        <button type="submit" class="btn-primary btn-block">Create account</button>
    </form>

    <p class="form-foot">
        Already registered? <a href="{{ route('login') }}" class="link-accent">Sign in</a>
    </p>
@endsection
