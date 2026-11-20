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
    {{-- Pertama kali buka di browser ini = "Welcome to CargoTrace"; sudah pernah masuk = "Welcome back"
         (resources/js/greeting.js). Tulisan di bawah = cadangan bila JavaScript mati. --}}
    <h2 class="form-title" data-welcome>Welcome to CargoTrace</h2>
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

    {{-- Akun demo (syarat dosen: live app + demo account). Tampil bila SHOW_DEMO_ACCOUNTS=true di .env;
         bila tidak diisi: tampil di lokal, tersembunyi di production. --}}
    @if (config('cargotrace.demo.show_accounts') ?? ! app()->isProduction())
        <details class="demo-box">
            <summary class="demo-summary">Demo accounts (password: <code>password</code>)</summary>
            <div class="demo-list">admin@cargotrace.test<br>operator@cargotrace.test<br>customer@cargotrace.test</div>
        </details>
    @endif
@endsection
