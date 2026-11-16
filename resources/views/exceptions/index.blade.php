{{--
 ============================================================
 BUAT APA : Daftar exception (gangguan shipment). Staf melihat semua; customer hanya
            exception pada shipment miliknya (dibatasi di ExceptionService, bukan di tampilan).
 URUTAN   : belum selesai dulu, lalu paling gawat, lalu terbaru.
 FITUR    : ringkasan angka, filter (nomor shipment, status, severity, jenis), pagination server-side.
 RESPONSIF: laptop = tabel (.only-desktop), HP = kartu (.only-mobile).
 PENANGANAN (investigasi / selesai) dilakukan di halaman detail shipment (#exceptions).
 ============================================================
--}}
@extends('layouts.app')
@section('title', auth()->user()->isCustomer() ? 'My exceptions' : 'Exceptions')

@section('content')
    {{-- ===== Ringkasan ===== --}}
    <div class="stat-grid stat-grid-3">
        <div class="card stat-card">
            <div class="stat-icon stat-icon-exception"><x-icon name="alert-triangle" class="icon-lg" /></div>
            <div><div class="stat-label">Open</div><div class="stat-value">{{ number_format($summary['open']) }}</div></div>
        </div>
        <div class="card stat-card">
            <div class="stat-icon stat-icon-transit"><x-icon name="search" class="icon-lg" /></div>
            <div><div class="stat-label">Investigating</div><div class="stat-value">{{ number_format($summary['investigating']) }}</div></div>
        </div>
        <div class="card stat-card">
            <div class="stat-icon stat-icon-delivered"><x-icon name="check-circle" class="icon-lg" /></div>
            <div><div class="stat-label">Resolved</div><div class="stat-value">{{ number_format($summary['resolved']) }}</div></div>
        </div>
    </div>

    {{-- ===== Filter ===== --}}
    <form method="GET" action="{{ route('exceptions.index') }}" class="card filter-bar" style="margin-top: 1.25rem">
        <div class="filter-grid">
            <div>
                <label for="q" class="field-label">Shipment number</label>
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" class="field-input" placeholder="e.g. CT-2026">
            </div>
            <div>
                <label for="status" class="field-label">Status</label>
                <select id="status" name="status" class="field-input">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\ShipmentException::STATUSES as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst(strtolower($status)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="severity" class="field-label">Severity</label>
                <select id="severity" name="severity" class="field-input">
                    <option value="">All severities</option>
                    @foreach (\App\Models\ShipmentException::SEVERITIES as $severity)
                        <option value="{{ $severity }}" @selected(($filters['severity'] ?? '') === $severity)>{{ ucfirst(strtolower($severity)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="type" class="field-label">Type</label>
                <select id="type" name="type" class="field-input">
                    <option value="">All types</option>
                    @foreach (\App\Models\ShipmentException::TYPES as $type)
                        <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ \App\Models\ShipmentException::typeLabel($type) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn-primary"><x-icon name="search" /> Apply</button>
            <a href="{{ route('exceptions.index') }}" class="btn-ghost">Reset</a>
        </div>
    </form>

    <div class="page-body">
        @if ($exceptions->isEmpty())
            <div class="empty-state">
                <p class="empty-title">No exceptions found</p>
                <p>Good news: nothing is wrong here, or the filters hide everything. Try resetting them.</p>
            </div>
        @else
            {{-- ===== Tabel (laptop) ===== --}}
            <div class="only-desktop">
                <div class="table-wrap">
                    <table class="data-table table-fit">
                        <thead>
                            <tr>
                                <th>Shipment</th>
                                @unless (auth()->user()->isCustomer()) <th>Customer</th> @endunless
                                <th>Exception</th>
                                <th>Severity</th>
                                <th>Status</th>
                                <th>Detected</th>
                                <th>Resolved</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($exceptions as $exception)
                                @php $shipment = $exception->shipment; @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('shipments.show', $shipment) }}#exceptions" class="cell-link">{{ $shipment->shipment_number }}</a>
                                        <div class="cell-sub">{{ $shipment->origin->city }} &rarr; {{ $shipment->destination->city }}</div>
                                    </td>
                                    @unless (auth()->user()->isCustomer()) <td>{{ $shipment->customer->name }}</td> @endunless
                                    <td>
                                        <strong>{{ $exception->label() }}</strong>
                                        @if ($exception->description) <div class="cell-sub">{{ \Illuminate\Support\Str::limit($exception->description, 70) }}</div> @endif
                                    </td>
                                    <td><span class="chip {{ $exception->severityChip() }}">{{ $exception->severity }}</span></td>
                                    <td><span class="chip {{ $exception->statusChip() }}">{{ strtoupper($exception->status) }}</span></td>
                                    <td>{{ $exception->detected_at->format('d M Y') }}<div class="cell-sub">{{ $exception->detected_at->format('H:i') }}</div></td>
                                    <td>@if ($exception->resolved_at) {{ $exception->resolved_at->format('d M Y') }}<div class="cell-sub">{{ $exception->resolved_at->format('H:i') }}</div>@else <span class="cell-sub">-</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ===== Kartu (HP) ===== --}}
            <div class="only-mobile item-list">
                @foreach ($exceptions as $exception)
                    @php $shipment = $exception->shipment; @endphp
                    <a href="{{ route('shipments.show', $shipment) }}#exceptions" class="item-card">
                        <div class="item-card-top">
                            <div class="item-card-title">{{ $exception->label() }}</div>
                            <span class="chip {{ $exception->severityChip() }}">{{ $exception->severity }}</span>
                        </div>
                        <div class="item-card-route">{{ $shipment->shipment_number }}<br>{{ $shipment->origin->city }} <span>&rarr;</span> {{ $shipment->destination->city }}</div>
                        <div class="meta-grid">
                            <div><div class="meta-label">Status</div><div class="meta-value"><span class="chip {{ $exception->statusChip() }}">{{ strtoupper($exception->status) }}</span></div></div>
                            <div><div class="meta-label">Detected</div><div class="meta-value">{{ $exception->detected_at->format('d M Y, H:i') }}</div></div>
                        </div>
                    </a>
                @endforeach
            </div>

            {{ $exceptions->links('vendor.pagination.cargotrace') }}
        @endif
    </div>
@endsection
