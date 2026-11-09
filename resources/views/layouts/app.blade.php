{{--
 ============================================================
 BUAT APA : Kerangka halaman SETELAH LOGIN (sidebar + topbar + isi).
 CATATAN  : Kelas (app-shell, sidebar, nav-link, ...) ada di resources/css/app.css.
 CARA PAKAI di halaman:
     @extends('layouts.app')
     @section('title', 'Dashboard')
     @section('content') ... @endsection
 @stack('scripts') = tempat halaman menyelipkan JavaScript (Leaflet, Chart.js).
 MENU     : sekarang baru Dashboard. Menu lain ditambah per tahap, per peran.
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

    <aside class="sidebar">
        <div class="sidebar-brand">Cargo<span class="brand-accent">Trace</span></div>

        <nav class="nav-list">
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}">Dashboard</a>

            {{-- Menu staf (admin + operator): ditambah mulai P9 --}}
            @if ($user->hasRole('admin', 'operator'))
                {{-- contoh nanti: <a href="{{ route('shipments.index') }}" class="nav-link">Shipments</a> --}}
            @endif

            {{-- Menu khusus admin: ditambah mulai P9 --}}
            @if ($user->isAdmin())
                {{-- contoh nanti: Carriers, Locations, Users, Settings, Audit log --}}
            @endif

            {{-- Menu customer: ditambah mulai P9 --}}
            @if ($user->isCustomer())
                {{-- contoh nanti: My shipments --}}
            @endif
        </nav>

        <div class="sidebar-user">
            <div class="sidebar-name">{{ $user->name }}</div>
            <div class="sidebar-role">{{ $user->role }}</div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf {{-- token anti-CSRF wajib di setiap form POST --}}
                <button type="submit" class="sidebar-signout">Sign out</button>
            </form>
        </div>
    </aside>

    <div class="app-content">
        <header class="mobile-bar">
            <span class="auth-logo">Cargo<span class="brand-accent">Trace</span></span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="mobile-signout">Sign out</button>
            </form>
        </header>

        <main class="page-main">
            <h1 class="page-title">@yield('title', 'Dashboard')</h1>
            <div class="page-rule"></div>

            {{-- Pesan sukses / gagal dari controller: ->with('success', '...') --}}
            @if (session('success')) <div class="flash-success">{{ session('success') }}</div> @endif
            @if (session('error'))   <div class="flash-error">{{ session('error') }}</div> @endif

            <div class="page-body">
                @yield('content')
            </div>
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
