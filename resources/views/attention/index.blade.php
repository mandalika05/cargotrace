{{--
 ============================================================
 BUAT APA : Antrean "Needs attention" untuk staf: shipment yang terlambat atau
            punya exception belum selesai, PALING GAWAT di atas. Inilah inti ide
            "control tower": operator langsung tahu apa yang harus ditangani dulu.
 DATA     : $queue dari AttentionService (skor, prioritas, alasan), $thresholds.
 ============================================================
--}}
@extends('layouts.app')
@section('title', 'Needs attention')

@section('content')
    <div class="card panel">
        <h2 class="panel-title">How the queue is ranked</h2>
        <p class="exc-empty" style="margin-bottom: .6rem">
            A shipment appears here when it is delayed (ETA later than the expected arrival, or overdue) or has an exception that is not resolved yet.
            Score = delay points (LOW 10, MEDIUM 30, HIGH 60) + exception points (LOW 10, MEDIUM 20, HIGH 40, +5 while still open).
            Delay limits now: LOW up to {{ $thresholds['low_max_hours'] }}h, MEDIUM up to {{ $thresholds['medium_max_hours'] }}h, HIGH above.
        </p>
        <div class="cell-actions" style="justify-content: flex-start">
            <span class="attn-rank attn-rank-critical">Critical 70+</span>
            <span class="attn-rank attn-rank-high">High 30+</span>
            <span class="attn-rank attn-rank-watch">Watch</span>
        </div>
    </div>

    <div class="page-body">
        @if ($queue->isEmpty())
            <div class="empty-state">
                <p class="empty-title">All clear</p>
                <p>No shipment is delayed or has an open exception right now.</p>
            </div>
        @else
            <div class="attn-list">
                @foreach ($queue as $entry)
                    @php $shipment = $entry['shipment']; $p = strtolower($entry['priority']); @endphp
                    <a href="{{ route('shipments.show', $shipment) }}#{{ $entry['exceptions']->isNotEmpty() ? 'exceptions' : 'delay' }}" class="attn-row attn-{{ $p }}">
                        <div><span class="attn-rank attn-rank-{{ $p }}">{{ $entry['priority'] }}</span></div>
                        <div>
                            <div class="attn-title">{{ $shipment->shipment_number }} <span class="chip {{ $shipment->statusChip() }}" style="margin-left:.4rem">{{ $shipment->statusLabel() }}</span></div>
                            <div class="attn-route">{{ $shipment->origin->city }} &rarr; {{ $shipment->destination->city }} &middot; {{ $shipment->customer->name }} &middot; {{ $shipment->carrier->name }}</div>
                            <ul class="attn-reasons">
                                @foreach ($entry['reasons'] as $reason) <li>{{ $reason }}</li> @endforeach
                            </ul>
                        </div>
                        <div class="attn-score">Score<strong>{{ $entry['score'] }}</strong></div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endsection
