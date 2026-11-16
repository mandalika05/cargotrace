{{--
 ============================================================
 BUAT APA : Settings (khusus Admin): ambang delay LOW / MEDIUM / HIGH.
            Disimpan di tabel settings, jadi bisa diganti sesuai skenario kelompok
            tanpa mengubah kode (syarat slide 8 dan contoh pertanyaan interview slide 19).
 ============================================================
--}}
@extends('layouts.app')
@section('title', 'Settings')

@section('content')
<div class="detail-grid">
    <div class="detail-main">
        <div class="card panel">
            <h2 class="panel-title">Delay thresholds</h2>
            <p class="exc-empty">
                A shipment is <strong>delayed</strong> when its ETA is later than the expected arrival. The size of the delay decides the severity.
            </p>

            <form method="POST" action="{{ route('settings.update') }}" class="form-stack">
                @csrf
                @method('PUT')
                <div class="form-grid">
                    <div>
                        <label for="delay_low_max_hours" class="field-label">LOW up to (hours)</label>
                        <input id="delay_low_max_hours" name="delay_low_max_hours" type="number" min="1" max="168" step="1" class="field-input" required
                               value="{{ old('delay_low_max_hours', $thresholds['low_max_hours']) }}">
                        @error('delay_low_max_hours') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="delay_medium_max_hours" class="field-label">MEDIUM up to (hours)</label>
                        <input id="delay_medium_max_hours" name="delay_medium_max_hours" type="number" min="2" max="720" step="1" class="field-input" required
                               value="{{ old('delay_medium_max_hours', $thresholds['medium_max_hours']) }}">
                        @error('delay_medium_max_hours') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p class="form-hint">Anything longer than the MEDIUM limit is HIGH. The MEDIUM limit must be larger than the LOW limit.</p>
                <div class="form-actions" style="margin-top: .5rem">
                    <button type="submit" class="btn-primary">Save thresholds</button>
                </div>
            </form>
        </div>
    </div>

    <div class="detail-side">
        <div class="card panel">
            <h2 class="panel-title">Current rule</h2>
            <table class="rule-table">
                <thead><tr><th>Delay</th><th>Severity</th></tr></thead>
                <tbody>
                    <tr><td>more than 0 and up to {{ $thresholds['low_max_hours'] }}h</td><td><span class="chip chip-delay">LOW</span></td></tr>
                    <tr><td>up to {{ $thresholds['medium_max_hours'] }}h</td><td><span class="chip chip-delay">MEDIUM</span></td></tr>
                    <tr><td>more than {{ $thresholds['medium_max_hours'] }}h</td><td><span class="chip chip-exception">HIGH</span></td></tr>
                </tbody>
            </table>
            <p class="form-hint" style="margin-top: .8rem">Exactly {{ $thresholds['low_max_hours'] }}h is still LOW; one minute more is MEDIUM.</p>
        </div>
    </div>
</div>
@endsection
