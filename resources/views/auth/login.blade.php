{{--
 ============================================================
 BUAT APA : Halaman login. Form POST ke /login (AuthController@login).
 @csrf    : token anti-CSRF. @error = menampilkan pesan validasi per field.
 old('email') : isi ulang email kalau login gagal (password tidak diisi ulang).
 ============================================================
--}}
@extends('layouts.guest')
@section('title', 'Sign in')

@section('content')
    <h2 class="form-title">Welcome back</h2>
    <p class="form-sub">Sign in to your CargoTrace account.</p>

    <form method="POST" action="{{ url('/login') }}" class="form-stack">
        @csrf
        <div>
            <label for="email" class="field-label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="field-input" placeholder="you@company.com">
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="field-label">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" class="field-input">
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <label class="check-row">
            <input type="checkbox" name="remember" class="check-box">
            Keep me signed in
        </label>

        <button type="submit" class="btn-primary btn-block">Sign in</button>
    </form>

    <p class="form-foot">
        New customer? <a href="{{ route('register') }}" class="link-accent">Create an account</a>
    </p>

    {{-- Petunjuk akun demo: hanya muncul di lokal/staging, hilang di production --}}
    @unless (app()->isProduction())
        <div class="demo-box">
            <div class="demo-title">Demo accounts (password: <code>password</code>)</div>
            admin@cargotrace.test<br>operator@cargotrace.test<br>customer@cargotrace.test
        </div>
    @endunless
@endsection
