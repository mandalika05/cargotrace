{{--
 ============================================================
 BUAT APA : Kartu LOKASI TERKINI (kolom kanan, paling atas).
            Diambil dari event terbaru yang punya nama/koordinat lokasi
            (Shipment::currentLocationEvent). Data $current dari show.blade.php.
 ============================================================
--}}
@php
    // Pengaman: hitung sendiri kalau belum disiapkan oleh show.blade.php.
    $current ??= $shipment->currentLocationEvent();
@endphp
<div class="card panel loc-card">
    <div class="current-loc">
        <div class="current-loc-icon"><x-icon name="map-pin" class="icon-lg" /></div>
        <div>
            <div class="current-loc-label">Current location</div>
            @if ($current)
                <div class="current-loc-name">{{ $current->placeLabel() }}</div>
                <div class="current-loc-meta">Updated {{ $current->occurred_at->format('d M Y, H:i') }}</div>
            @else
                <div class="current-loc-name">{{ $shipment->origin->city }}</div>
                <div class="current-loc-meta">Not yet moving (at origin)</div>
            @endif
        </div>
    </div>
</div>
