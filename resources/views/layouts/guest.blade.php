{{--
 ============================================================
 BUAT APA : Kerangka halaman SEBELUM LOGIN (login, register).
            Kiri : panel hijau hidup (huruf bergelombang, rute pengiriman
                   bergerak, cahaya melayang). Kanan: kartu formulir.
            Batas kiri-kanan = tepi bergelombang (bukan garis lurus).
 CATATAN  : Semua kelas ada di resources/css/app.css (blok "Halaman sebelum login").
            Animasi: wave (huruf), dash (garis rute), drift (cahaya).
 CARA PAKAI di halaman:
     @extends('layouts.guest')
     @section('title', 'Sign in')
     @section('content') ... @endsection
 ============================================================
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Sign in') - CargoTrace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@php
    // Judul dipecah per baris -> per kata -> per huruf, supaya tiap huruf bisa
    // dianimasikan bergantian. --i = urutan huruf (dipakai untuk jeda animasi).
    $headlineLines = [
        ['text' => 'Every shipment,', 'class' => ''],
        ['text' => 'always in sight.', 'class' => 'headline-accent'],
    ];
    $i = 0;
    // Satu jalur rute dipakai dua kali: untuk garis putus-putus dan untuk paket yang bergerak
    $routePath = 'M40 190 C150 40 270 250 390 120 S 520 40 575 70';
@endphp

<div class="auth-page">

    <aside class="auth-aside">
        <div class="auth-glow" style="top:-4rem; left:-3rem"></div>
        <div class="auth-glow auth-glow-b" style="bottom:2rem; right:3rem"></div>

        <div class="auth-top">
            <div class="auth-logo">Cargo<span class="brand-accent">Trace</span></div>
        </div>

        <div class="auth-middle">
            <div class="auth-rule"></div>
            <h2 class="auth-headline" aria-label="Every shipment, always in sight.">
                @foreach ($headlineLines as $line)
                    <span class="headline-line {{ $line['class'] }}" aria-hidden="true">
                        @foreach (explode(' ', $line['text']) as $word)
                            <span class="headline-word">@foreach (mb_str_split($word) as $char)<span class="wave-char" style="--i: {{ $i++ }}">{{ $char }}</span>@endforeach</span>
                        @endforeach
                    </span>
                @endforeach
            </h2>
            <p class="auth-lead">One control tower for tracking, delays and exceptions, so your team acts on what matters first.</p>
        </div>

        <div class="auth-bottom">
            {{-- Ilustrasi rute: Singapore -> Rotterdam, paket bergerak sepanjang garis --}}
            <svg class="auth-route" viewBox="0 0 620 260" fill="none" aria-hidden="true">
                <path d="{{ $routePath }}" class="route-line" stroke="#d9b26a" stroke-width="2" stroke-linecap="round" opacity=".85"/>

                {{-- Titik asal --}}
                <circle cx="40" cy="190" r="6" fill="#d9b26a"/>
                <circle cx="40" cy="190" r="6" fill="none" stroke="#d9b26a" stroke-width="2">
                    <animate attributeName="r" values="6;22;6" dur="2.6s" repeatCount="indefinite"/>
                    <animate attributeName="opacity" values=".7;0;.7" dur="2.6s" repeatCount="indefinite"/>
                </circle>
                <text x="40" y="228" fill="#fffefb" fill-opacity=".6" font-size="12" font-weight="700" letter-spacing="1.5" text-anchor="middle">SINGAPORE</text>

                {{-- Titik tujuan --}}
                <circle cx="575" cy="70" r="6" fill="#d9b26a"/>
                <circle cx="575" cy="70" r="6" fill="none" stroke="#d9b26a" stroke-width="2">
                    <animate attributeName="r" values="6;22;6" dur="2.6s" begin="1.3s" repeatCount="indefinite"/>
                    <animate attributeName="opacity" values=".7;0;.7" dur="2.6s" begin="1.3s" repeatCount="indefinite"/>
                </circle>
                <text x="575" y="108" fill="#fffefb" fill-opacity=".6" font-size="12" font-weight="700" letter-spacing="1.5" text-anchor="middle">ROTTERDAM</text>

                {{-- Paket yang bergerak --}}
                <g>
                    <rect x="-7" y="-7" width="14" height="14" rx="4" fill="#fffefb" stroke="#d9b26a" stroke-width="2"/>
                    <animateMotion dur="9s" repeatCount="indefinite" path="{{ $routePath }}" rotate="0"/>
                </g>
            </svg>
            <p class="auth-foot">Global shipment visibility &amp; tracking platform</p>
        </div>

        {{-- Tepi bergelombang pengganti garis lurus; bentuknya bergerak pelan --}}
        <svg class="auth-edge" viewBox="0 0 80 800" preserveAspectRatio="none" aria-hidden="true">
            <path d="M40 0 C70 100 10 200 40 300 C70 400 10 500 40 600 C70 700 10 760 40 800 L80 800 L80 0 Z">
                <animate attributeName="d" dur="11s" repeatCount="indefinite"
                    values="M40 0 C70 100 10 200 40 300 C70 400 10 500 40 600 C70 700 10 760 40 800 L80 800 L80 0 Z;
                            M40 0 C10 100 70 200 40 300 C10 400 70 500 40 600 C10 700 70 760 40 800 L80 800 L80 0 Z;
                            M40 0 C70 100 10 200 40 300 C70 400 10 500 40 600 C70 700 10 760 40 800 L80 800 L80 0 Z"/>
            </path>
        </svg>
    </aside>

    {{-- Pita hijau versi HP (di laptop disembunyikan oleh CSS) --}}
    <header class="auth-ribbon">
        <div class="auth-ribbon-logo">Cargo<span class="brand-accent">Trace</span></div>
        <p class="auth-ribbon-tag">Every shipment, always in sight.</p>
        <svg class="auth-ribbon-route" viewBox="0 0 300 40" fill="none" aria-hidden="true">
            <path d="M6 30 C70 2 130 38 190 16 S270 4 294 12" class="route-line" stroke="#d9b26a" stroke-width="2" stroke-linecap="round" opacity=".85"/>
            <circle cx="6" cy="30" r="4" fill="#d9b26a"/>
            <circle cx="294" cy="12" r="4" fill="#d9b26a"/>
            <rect x="-5" y="-5" width="10" height="10" rx="3" fill="#fffefb" stroke="#d9b26a" stroke-width="1.5">
                <animateMotion dur="7s" repeatCount="indefinite" path="M6 30 C70 2 130 38 190 16 S270 4 294 12"/>
            </rect>
        </svg>
    </header>

    <main class="auth-main">
        <div class="auth-box">
            <div class="card auth-card">
                @yield('content')
            </div>
        </div>
    </main>
</div>
</body>
</html>
