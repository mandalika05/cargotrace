{{--
 ============================================================
 BUAT APA : Kartu "Delay & ETA" (kolom kanan halaman detail shipment).
            Menampilkan hasil aturan delay (EtaService) lengkap dengan ALASAN dan
            nilai yang dipakai (syarat slide 8). Staf bisa memperbarui ETA dengan alasan;
            perubahan tercatat di timeline.
 DATA     : $shipment dari show.blade.php. Dihitung sendiri di sini (aman dipakai ulang).
 ============================================================
--}}
@php
    $delay = $delay ?? $shipment->delay();
    $canUpdateEta = auth()->user()->can('update', $shipment); // staf + shipment belum terminal
    $late = $delay->isDelayed();
    $high = $late && $delay->severity === 'HIGH';
    $etaNow = $shipment->status === 'DELIVERED' ? $shipment->delivered_at : $shipment->estimated_arrival_at;
@endphp
<div id="delay" class="card panel">
    <h2 class="panel-title">Delay &amp; ETA</h2>

    <div class="delay-head">
        <span class="chip {{ $delay->chipClass() }}">{{ $delay->label() }}</span>
        @if ($late)
            <span class="delay-big {{ $high ? 'delay-big-high' : 'delay-big-late' }}">{{ $delay->duration() }} {{ $delay->overdue ? 'overdue' : 'late' }}</span>
        @endif
    </div>

    <p class="delay-reason {{ $high ? 'delay-reason-high' : ($late ? 'delay-reason-late' : '') }}">{{ $delay->reason }}</p>

    <dl class="kv-list delay-values">
        <div><dt class="kv-key">Expected arrival</dt><dd class="kv-val">{{ $shipment->expected_arrival_at->format('d M Y, H:i') }}</dd></div>
        <div><dt class="kv-key">{{ $shipment->status === 'DELIVERED' ? 'Delivered' : 'Current ETA' }}</dt><dd class="kv-val">{{ $etaNow?->format('d M Y, H:i') ?? '-' }}</dd></div>
        <div><dt class="kv-key">Difference</dt><dd class="kv-val">
            @if ($delay->state === 'NOT_APPLICABLE') -
            @elseif ($delay->delayMinutes > 0) +{{ $delay->duration() }}
            @elseif ($delay->delayMinutes < 0) -{{ $delay->duration() }}
            @else 0m @endif
        </dd></div>
        <div><dt class="kv-key">Delay limits</dt><dd class="kv-val">LOW &le; {{ $delay->thresholds['low_max_hours'] }}h, MEDIUM &le; {{ $delay->thresholds['medium_max_hours'] }}h</dd></div>
    </dl>

    @if ($canUpdateEta)
        <details class="fold" @if ($errors->has('estimated_arrival_at') || $errors->has('reason')) open @endif>
            <summary><x-icon name="clock" /> Update ETA</summary>
            <div class="fold-body">
                <form method="POST" action="{{ route('shipments.eta.update', $shipment) }}" class="form-stack">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="estimated_arrival_at" class="field-label">New ETA</label>
                        <input id="estimated_arrival_at" name="estimated_arrival_at" type="datetime-local" class="field-input" required
                               value="{{ old('estimated_arrival_at', optional($shipment->estimated_arrival_at)->format('Y-m-d\TH:i')) }}">
                        @error('estimated_arrival_at') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="eta_reason" class="field-label">Reason</label>
                        <input id="eta_reason" name="reason" type="text" maxlength="300" class="field-input" required value="{{ old('reason') }}" placeholder="e.g. Port congestion at Colombo">
                        @error('reason') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="btn-primary">Save ETA</button>
                </form>
            </div>
        </details>
    @endif
</div>
