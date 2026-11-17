{{--
 ============================================================
 BUAT APA : Daftar shipment. Staf melihat semua; customer hanya miliknya
            (dibatasi di ShipmentService::paginate, bukan di tampilan).
 FITUR    : search, filter status, carrier, origin, destination, status delay, rentang
            tanggal, sort, pagination server-side (filter lanjutan di "More filters").
 RESPONSIF: laptop = tabel (.only-desktop), HP = kartu (.only-mobile).
 ============================================================
--}}
@extends('layouts.app')
@section('title', auth()->user()->isCustomer() ? 'My shipments' : 'Shipments')

@section('actions')
    @can('create', \App\Models\Shipment::class)
        <a href="{{ route('shipments.create') }}" class="btn-primary"><x-icon name="plus" /> New shipment</a>
    @endcan
@endsection

@section('content')
    {{-- ===== Filter ===== --}}
    <form method="GET" action="{{ route('shipments.index') }}" class="card filter-bar">
        <div class="filter-grid">
            <div>
                <label for="q" class="field-label">Search</label>
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" class="field-input"
                       placeholder="{{ auth()->user()->isCustomer() ? 'Shipment number' : 'Shipment number or customer' }}">
            </div>
            <div>
                <label for="status" class="field-label">Status</label>
                <select id="status" name="status" class="field-input">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\Shipment::STATUSES as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str_replace('_', ' ', $status) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="carrier_id" class="field-label">Carrier</label>
                <select id="carrier_id" name="carrier_id" class="field-input">
                    <option value="">All carriers</option>
                    @foreach ($carriers as $carrier)
                        <option value="{{ $carrier->id }}" @selected((string) ($filters['carrier_id'] ?? '') === (string) $carrier->id)>{{ $carrier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="sort" class="field-label">Sort by</label>
                <select id="sort" name="sort" class="field-input">
                    @foreach (['newest' => 'Newest first', 'oldest' => 'Oldest first', 'eta_asc' => 'ETA: earliest', 'eta_desc' => 'ETA: latest', 'number' => 'Shipment number'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['sort'] ?? 'newest') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- ===== Filter lanjutan (P13.2): origin, destination, status delay, rentang tanggal.
                  Terbuka otomatis bila salah satunya sedang dipakai. ===== --}}
        @php
            $hasAdvanced = collect(['origin_id', 'destination_id', 'delay', 'date_from', 'date_to'])->contains(fn ($k) => filled($filters[$k] ?? null));
        @endphp
        <details class="filter-more" @if ($hasAdvanced) open @endif>
            <summary>More filters</summary>
            <div class="filter-grid filter-grid-more">
                <div>
                    <label for="origin_id" class="field-label">Origin</label>
                    <select id="origin_id" name="origin_id" class="field-input">
                        <option value="">Any origin</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) ($filters['origin_id'] ?? '') === (string) $location->id)>{{ $location->name }} ({{ $location->city }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="destination_id" class="field-label">Destination</label>
                    <select id="destination_id" name="destination_id" class="field-input">
                        <option value="">Any destination</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) ($filters['destination_id'] ?? '') === (string) $location->id)>{{ $location->name }} ({{ $location->city }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="delay" class="field-label">Delay status</label>
                    <select id="delay" name="delay" class="field-input">
                        <option value="">Any</option>
                        @foreach (['delayed' => 'Delayed (any level)', 'low' => 'Delayed - low', 'medium' => 'Delayed - medium', 'high' => 'Delayed - high', 'on_time' => 'On time'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['delay'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date_field" class="field-label">Date by</label>
                    <select id="date_field" name="date_field" class="field-input">
                        @foreach (['eta' => 'ETA', 'departure' => 'Expected departure', 'created' => 'Created'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['date_field'] ?? 'eta') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date_from" class="field-label">From date</label>
                    <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="field-input">
                </div>
                <div>
                    <label for="date_to" class="field-label">To date</label>
                    <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="field-input">
                </div>
            </div>
        </details>

        @if ($errors->any())
            <ul class="filter-errors">
                @foreach ($errors->all() as $message) <li>{{ $message }}</li> @endforeach
            </ul>
        @endif

        <div class="filter-actions">
            <button type="submit" class="btn-primary"><x-icon name="search" /> Apply</button>
            <a href="{{ route('shipments.index') }}" class="btn-ghost">Reset</a>
            <a href="{{ route('exports.shipments', request()->query()) }}" class="btn-ghost"><x-icon name="file-text" /> Export CSV</a>
        </div>
    </form>

    <div class="page-body">
        @if ($shipments->isEmpty())
            <div class="empty-state">
                <p class="empty-title">No shipments found</p>
                <p>Try a different search or clear the filters.</p>
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
                                <th>Route</th>
                                <th>Carrier</th>
                                <th>ETA</th>
                                <th>Status</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($shipments as $shipment)
                                <tr>
                                    <td><a href="{{ route('shipments.show', $shipment) }}" class="cell-link">{{ $shipment->shipment_number }}</a></td>
                                    @unless (auth()->user()->isCustomer()) <td>{{ $shipment->customer->name }}</td> @endunless
                                    <td>
                                        {{ $shipment->origin->city }} &rarr; {{ $shipment->destination->city }}
                                        <div class="cell-sub">{{ $shipment->cargo_type }}</div>
                                    </td>
                                    <td>{{ $shipment->carrier->name }}</td>
                                    <td>{{ $shipment->estimated_arrival_at?->format('d M Y') }}
                                        <div class="cell-sub">{{ $shipment->estimated_arrival_at?->format('H:i') }}</div></td>
                                    <td class="cell-status">
                                        <span class="chip {{ $shipment->statusChip() }}">{{ $shipment->statusLabel() }}</span>
                                        @php $delay = $shipment->delay(); @endphp
                                        @if ($delay->isDelayed()) <div style="margin-top:.35rem"><span class="chip {{ $delay->chipClass() }}">{{ $delay->label() }}</span></div> @endif
                                    </td>
                                    <td class="progress-cell"><div class="progress"><div class="progress-bar" style="width: {{ $shipment->progressPercent() }}%"></div></div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ===== Kartu (HP) ===== --}}
            <div class="only-mobile item-list">
                @foreach ($shipments as $shipment)
                    <a href="{{ route('shipments.show', $shipment) }}" class="item-card">
                        <div class="item-card-top">
                            <div class="item-card-title">{{ $shipment->shipment_number }}</div>
                            <span class="chip {{ $shipment->statusChip() }}">{{ $shipment->statusLabel() }}</span>
                        </div>
                        <div class="item-card-route">{{ $shipment->origin->city }} <span>&rarr;</span> {{ $shipment->destination->city }}</div>
                        @php $delay = $shipment->delay(); @endphp
                        @if ($delay->isDelayed()) <div style="margin-top:.5rem"><span class="chip {{ $delay->chipClass() }}">{{ $delay->label() }}</span></div> @endif
                        <div class="progress"><div class="progress-bar" style="width: {{ $shipment->progressPercent() }}%"></div></div>
                        <div class="meta-grid">
                            <div><div class="meta-label">Carrier</div><div class="meta-value">{{ $shipment->carrier->name }}</div></div>
                            <div><div class="meta-label">ETA</div><div class="meta-value">{{ $shipment->estimated_arrival_at?->format('d M Y') }}</div></div>
                            @unless (auth()->user()->isCustomer())
                                <div><div class="meta-label">Customer</div><div class="meta-value">{{ $shipment->customer->name }}</div></div>
                            @endunless
                            <div><div class="meta-label">Cargo</div><div class="meta-value">{{ $shipment->cargo_type }}</div></div>
                        </div>
                    </a>
                @endforeach
            </div>

            {{ $shipments->links('vendor.pagination.cargotrace') }}
        @endif
    </div>
@endsection
