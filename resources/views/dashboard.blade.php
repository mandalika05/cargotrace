{{--
 ============================================================
 BUAT APA : Halaman dashboard sementara (kerangka). Dashboard asli
            (kartu angka, grafik, peta, antrean Needs attention) dibuat di P13.
 ============================================================
--}}
@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    <div class="card welcome-card">
        <p class="eyebrow">Signed in as {{ auth()->user()->role }}</p>
        <h2 class="welcome-title">Welcome, {{ auth()->user()->name }}</h2>
        <p class="welcome-text">
            Authentication and role-based access are working. Shipments, tracking, delay detection
            and the live map are added in the next stages.
        </p>

        {{-- Contoh chip status (pratinjau warna penanda status) --}}
        <div class="chip-row">
            <span class="chip chip-neutral">CREATED</span>
            <span class="chip chip-transit">IN TRANSIT</span>
            <span class="chip chip-delay">DELAYED</span>
            <span class="chip chip-exception">EXCEPTION</span>
            <span class="chip chip-delivered">DELIVERED</span>
        </div>
    </div>
@endsection
