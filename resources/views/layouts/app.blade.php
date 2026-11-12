{{--
 ============================================================
 BUAT APA : Kerangka halaman SETELAH LOGIN.
            Laptop : sidebar kiri tetap.
            HP     : bar atas + tombol hamburger yang membuka daftar menu.
 CATATAN  : Gaya ada di resources/css/app.css, polish.css dan auth.css (sidebar, logo, profil). Menu ada di
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
    <meta name="theme-color" content="#0f2f2a">
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
        <div class="sidebar-brand">
            <x-logo-mark />
            <span class="brand-word">Cargo<span class="brand-accent">Trace</span></span>
        </div>

        <nav class="nav-list">
            @include('layouts.partials.nav')
        </nav>


        {{-- Ilustrasi pelabuhan (SVG tertanam, hanya hiasan): derek, kapal berisi kontainer, air --}}
        <div class="sidebar-art" aria-hidden="true">
            <svg viewBox="0 0 220 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                <g stroke="#7fe8d4" stroke-opacity=".22">
                    <circle cx="186" cy="26" r="12"/><circle cx="186" cy="26" r="22"/><circle cx="186" cy="26" r="32"/>
                </g>
                <circle cx="186" cy="26" r="2.5" fill="#f4b740"/>
                <g stroke="#7fd1bf" stroke-opacity=".75" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 100V38M48 100V38M20 38H96M20 60L48 38M20 82L48 60"/>
                    <path d="M76 38V56"/>
                </g>
                <rect x="69" y="56" width="14" height="9" rx="1.5" fill="#f4b740"/>
                <g>
                    <path d="M62 84H196L186 99H72Z" fill="#b8893b" fill-opacity=".92"/>
                    <rect x="74" y="73" width="16" height="11" rx="1.5" fill="#14b8a6"/>
                    <rect x="91" y="73" width="16" height="11" rx="1.5" fill="#f4b740"/>
                    <rect x="108" y="73" width="16" height="11" rx="1.5" fill="#f0705b"/>
                    <rect x="125" y="73" width="16" height="11" rx="1.5" fill="#52c47f"/>
                    <rect x="82" y="62" width="16" height="11" rx="1.5" fill="#52c47f"/>
                    <rect x="99" y="62" width="16" height="11" rx="1.5" fill="#14b8a6"/>
                    <rect x="116" y="62" width="16" height="11" rx="1.5" fill="#f4b740"/>
                    <rect x="150" y="60" width="26" height="24" rx="2" fill="#0a2420" stroke="#f4d58a" stroke-opacity=".7"/>
                    <path d="M155 68H171M155 74H171" stroke="#f4d58a" stroke-opacity=".6" stroke-linecap="round"/>
                </g>
                <path d="M0 102q11-6 22 0t22 0t22 0t22 0t22 0t22 0t22 0t22 0t22 0t22 0" stroke="#14b8a6" stroke-opacity=".7" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M0 111q11-6 22 0t22 0t22 0t22 0t22 0t22 0t22 0t22 0t22 0t22 0" stroke="#14b8a6" stroke-opacity=".35" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
        </div>

        <div class="sidebar-user">
            <div class="profile">
                <span class="avatar">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                <div class="profile-text">
                    <div class="profile-name">{{ $user->name }}</div>
                    <span class="profile-role">{{ $user->role }}</span>
                </div>
            </div>
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
                <span class="mobile-brand">
                    <x-logo-mark />
                    <span class="brand-word">Cargo<span class="brand-accent">Trace</span></span>
                </span>
                <button type="button" id="menu-toggle" class="menu-button" aria-label="Open menu" aria-expanded="false" aria-controls="mobile-menu">
                    <x-icon name="menu" class="icon-lg" />
                </button>
            </div>
            <div id="mobile-menu" class="mobile-menu">
                <nav class="mobile-nav">
                    @include('layouts.partials.nav')
                </nav>
                <div class="mobile-user">
                    <div class="profile">
                        <span class="avatar">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <div class="profile-text">
                            <div class="profile-name">{{ $user->name }}</div>
                            <span class="profile-role">{{ $user->role }}</span>
                        </div>
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
