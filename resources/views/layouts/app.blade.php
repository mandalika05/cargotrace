{{--
 ============================================================
 BUAT APA : Kerangka halaman SETELAH LOGIN.
            Laptop : sidebar kiri tetap.
            HP     : bar atas + tombol hamburger yang membuka daftar menu.
 CATATAN  : Semua kelas ada di resources/css/app.css. Menu ada di
            layouts/partials/nav.blade.php (satu daftar untuk laptop dan HP).
 CARA PAKAI di halaman:
     @extends('layouts.app')
     @section('title', 'Judul')
     @section('actions') tombol di kanan judul (opsional) @endsection
     @section('content') isi halaman @endsection
 @stack('scripts') = tempat halaman menyelipkan JavaScript (Leaflet, Chart.js).
 ============================================================
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - CargoTrace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
@php $user = auth()->user(); @endphp

<div class="app-shell">

    {{-- ===== Sidebar (laptop) ===== --}}
    <aside class="sidebar">
        <div class="sidebar-brand">Cargo<span class="brand-accent">Trace</span></div>

        <nav class="nav-list">
            @include('layouts.partials.nav')
        </nav>

        <div class="sidebar-user">
            <div class="sidebar-name">{{ $user->name }}</div>
            <div class="sidebar-role">{{ $user->role }}</div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf {{-- token anti-CSRF wajib di setiap form POST --}}
                <button type="submit" class="sidebar-signout"><x-icon name="log-out" /> Sign out</button>
            </form>
        </div>
    </aside>

    <div class="app-content">

        {{-- ===== Bar atas + menu hamburger (HP) ===== --}}
        <header class="mobile-head">
            <div class="mobile-bar">
                <span class="auth-logo">Cargo<span class="brand-accent">Trace</span></span>
                <button type="button" id="menu-toggle" class="menu-button" aria-label="Open menu" aria-expanded="false" aria-controls="mobile-menu">
                    <x-icon name="menu" class="icon-lg" />
                </button>
            </div>
            <div id="mobile-menu" class="mobile-menu">
                <nav class="mobile-nav">
                    @include('layouts.partials.nav')
                </nav>
                <div class="mobile-user">
                    <div>
                        <div class="sidebar-name">{{ $user->name }}</div>
                        <div class="sidebar-role">{{ $user->role }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="mobile-signout"><x-icon name="log-out" /> Sign out</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="page-main">
            <div class="page-head">
                <div>
                    <h1 class="page-title">@yield('title', 'Dashboard')</h1>
                    <div class="page-rule"></div>
                </div>
                <div class="page-actions">@yield('actions')</div>
            </div>

            {{-- Pesan sukses / gagal dari controller: ->with('success', '...') --}}
            @if (session('success')) <div class="flash-success">{{ session('success') }}</div> @endif
            @if (session('error'))   <div class="flash-error">{{ session('error') }}</div> @endif

            <div class="page-body">
                @yield('content')
            </div>
        </main>
    </div>
</div>

{{-- Buka/tutup menu hamburger (JavaScript sederhana, tanpa library) --}}
<script>
    (function () {
        var button = document.getElementById('menu-toggle');
        var menu = document.getElementById('mobile-menu');
        if (!button || !menu) return;
        button.addEventListener('click', function () {
            var open = menu.classList.toggle('is-open');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    })();
</script>
@stack('scripts')
</body>
</html>
