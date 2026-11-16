{{--
 ============================================================
 BUAT APA : Halaman LACAK PUBLIK (/track), tanpa login. Masukkan nomor shipment, lihat
            status, rute, jadwal, lencana delay, dan riwayat perjalanan.
 PRIVASI  : tidak menampilkan customer, carrier, catatan internal, atau exception.
 ============================================================
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Track a shipment - CargoTrace</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="pub-wrap">
    <header class="pub-head">
        <a href="{{ route('login') }}" class="pub-brand">
            <x-logo-mark class="logo-mark" />
            <span>Cargo<span class="brand-accent">Trace</span></span>
        </a>
        <a href="{{ route('login') }}" class="btn-ghost">Sign in</a>
    </header>

    <main class="pub-main">
        <h1 class="pub-title">Track your shipment</h1>
        <p class="pub-lead">Enter the shipment number from your booking to see where your cargo is.</p>

        <form method="GET" action="{{ route('track') }}" class="card pub-form">
            <label for="number" class="field-label">Shipment number</label>
            <div class="pub-row">
                <input id="number" name="number" type="text" maxlength="40" class="field-input" value="{{ $number }}" placeholder="e.g. CT-24771248-abcd" required autofocus>
                <button type="submit" class="btn-primary">Track</button>
            </div>
            @if ($error) <p class="field-error">{{ $error }}</p> @endif
        </form>

        @if ($searched && ! $error && ! $shipment)
            <div class="card panel" style="margin-top:1.25rem">
                <p><strong>No shipment found.</strong> Check the number and try again.</p>
            </div>
        @endif

        @if ($shipment)
            <div class="card panel pub-result">
                <div class="pub-result-head">
                    <div>
                        <div class="pub-number">{{ $shipment->shipment_number }}</div>
                        <div class="pub-route">{{ $shipment->origin->city }}, {{ $shipment->origin->country }} &rarr; {{ $shipment->destination->city }}, {{ $shipment->destination->country }}</div>
                    </div>
                    <div class="pub-chips">
                        <span class="chip {{ $shipment->statusChip() }}">{{ $shipment->statusLabel() }}</span>
                        @if ($delay->isDelayed()) <span class="chip {{ $delay->chipClass() }}">{{ $delay->label() }}</span> @endif
                    </div>
                </div>

                <dl class="pub-facts">
                    <div><dt>Expected arrival</dt><dd>{{ $shipment->expected_arrival_at->format('d M Y, H:i') }}</dd></div>
                    <div><dt>{{ $shipment->status === 'DELIVERED' ? 'Delivered' : 'Current ETA' }}</dt>
                        <dd>{{ ($shipment->status === 'DELIVERED' ? $shipment->delivered_at : $shipment->estimated_arrival_at)?->format('d M Y, H:i') ?? '-' }}</dd></div>
                </dl>

                <h2 class="panel-title" style="margin-top:1.5rem">Journey</h2>
                @if ($events->isEmpty())
                    <p class="exc-empty">No tracking updates yet.</p>
                @else
                    <ol class="pub-steps">
                        @foreach ($events->reverse() as $event)
                            <li>
                                <span class="pub-dot"></span>
                                <div>
                                    <div class="pub-step-title">{{ $event->label() }}@if ($event->location_name) <span class="pub-step-place">&middot; {{ $event->location_name }}</span>@endif</div>
                                    <div class="pub-step-time">{{ $event->occurred_at->format('d M Y, H:i') }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        @endif
    </main>
</div>
</body>
</html>
