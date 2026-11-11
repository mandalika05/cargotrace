{{--
 ============================================================
 BUAT APA : Kartu TRACKING TIMELINE (kolom kiri halaman detail shipment).
 DATA     : $events, $latestId dihitung di show.blade.php.
 URUTAN   : kronologis (paling lama di atas, terbaru di bawah + lencana "Latest").
            Baris "Shipment created" diambil dari created_at shipment.
 id="timeline" : tujuan #timeline setelah menambah event.
 ============================================================
--}}
@php
    // Pengaman: kalau show.blade.php tidak menyiapkan data, hitung sendiri di sini.
    $events ??= $shipment->trackingEvents()->with(['creator:id,name'])->chronological()->get();
    $latestId ??= $events->last()?->id;
@endphp
<div id="timeline" class="card panel">
    <h2 class="panel-title">Tracking timeline</h2>

    <ol class="tl-wrap">
        <li class="tl-item">
            <span class="tl-dot tl-dot-neutral"></span>
            <div class="tl-head">
                <span class="tl-title">Shipment created</span>
                <span class="tl-time">{{ $shipment->created_at->format('d M Y, H:i') }}</span>
            </div>
            <div class="tl-place">{{ $shipment->origin->name }}, {{ $shipment->origin->country }}</div>
            @if ($shipment->creator) <div class="tl-by">by {{ $shipment->creator->name }}</div> @endif
        </li>

        @foreach ($events as $event)
            <li class="tl-item">
                <span class="tl-dot {{ $event->dotClass() }}"></span>
                <div class="tl-head">
                    <span class="tl-title">{{ $event->label() }}@if ($event->id === $latestId)<span class="tl-latest-badge">Latest</span>@endif</span>
                    <span class="tl-time">{{ $event->occurred_at->format('d M Y, H:i') }}</span>
                </div>
                @if ($event->placeLabel()) <div class="tl-place">{{ $event->placeLabel() }}</div> @endif
                @if ($event->description) <div class="tl-desc">{{ $event->description }}</div> @endif
                @if ($event->creator) <div class="tl-by">by {{ $event->creator->name }}</div> @endif
            </li>
        @endforeach
    </ol>

    @if ($events->isEmpty())
        <p class="cell-sub" style="margin-top: 1rem">No tracking events yet. Updates will appear here as the shipment moves.</p>
    @endif
</div>
