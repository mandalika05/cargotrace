{{--
 ============================================================
 BUAT APA : Halaman Audit log (P13.3, khusus admin): riwayat siapa mengubah apa dan kapan.
 ISI      : waktu, pelaku ("System" bila tanpa login), aksi, data yang diubah, perubahan
            (nilai lama -> baru), alamat IP. Data hanya bisa DITAMBAH, tidak bisa diubah/dihapus.
 FILTER   : jenis data, aksi, pelaku, rentang tanggal, cari label. Pagination 20 per halaman.
 DATA     : $logs (paginator), $filters, $existingShipments (id shipment yang masih ada), $staff.
 ============================================================
--}}
@extends('layouts.app')
@section('title', 'Audit log')

@section('content')
    <form method="GET" action="{{ route('audit-logs.index') }}" class="card filter-bar">
        @if ($errors->any())
            <ul class="filter-errors">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        @endif
        <div class="filter-grid">
            <div>
                <label for="q" class="field-label">Search</label>
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" class="field-input" placeholder="Shipment no., email, name...">
            </div>
            <div>
                <label for="entity" class="field-label">Data type</label>
                <select id="entity" name="entity" class="field-input">
                    <option value="">All types</option>
                    @foreach (\App\Models\AuditLog::ENTITIES as $entity)
                        <option value="{{ $entity }}" @selected(($filters['entity'] ?? '') === $entity)>{{ ucfirst(str_replace('_', ' ', $entity)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="action_type" class="field-label">Action</label>
                <select id="action_type" name="action_type" class="field-input">
                    <option value="">All actions</option>
                    @foreach (\App\Models\AuditLog::ACTION_TYPES as $type)
                        <option value="{{ $type }}" @selected(($filters['action_type'] ?? '') === $type)>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="user_id" class="field-label">Done by</label>
                <select id="user_id" name="user_id" class="field-input">
                    <option value="">Anyone</option>
                    @foreach ($staff as $person)
                        <option value="{{ $person->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $person->id)>{{ $person->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="date_from" class="field-label">From</label>
                <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="field-input">
            </div>
            <div>
                <label for="date_to" class="field-label">To</label>
                <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="field-input">
            </div>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn-primary"><x-icon name="search" /> Apply</button>
            <a href="{{ route('audit-logs.index') }}" class="btn-ghost">Reset</a>
        </div>
    </form>

    <div class="page-body">
        @if ($logs->isEmpty())
            <div class="empty-state">
                <p class="empty-title">No audit entries found</p>
                <p>Changes made in the app are recorded here. Try resetting the filters.</p>
            </div>
        @else
            {{-- Kolom "Changes" menampilkan paling banyak 4 baris agar tabel tetap rapi --}}
            <div class="table-wrap">
                <table class="data-table table-fit audit-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Done by</th>
                            <th>Action</th>
                            <th>Data</th>
                            <th>Changes</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            @php $lines = $log->changeLines(); @endphp
                            <tr>
                                <td>{{ $log->created_at->format('d M Y') }}<div class="cell-sub">{{ $log->created_at->format('H:i:s') }}</div></td>
                                <td>
                                    @if ($log->user)
                                        {{ $log->user->name }}<div class="cell-sub">{{ $log->user->role }}</div>
                                    @else
                                        <span class="cell-sub">System</span>
                                    @endif
                                </td>
                                <td><span class="chip {{ $log->actionChip() }}">{{ $log->actionLabel() }}</span></td>
                                <td>
                                    <div class="cell-sub">{{ $log->entityLabelText() }}</div>
                                    @if ($log->entity_type === 'shipment' && in_array($log->entity_id, $existingShipments, true))
                                        <a href="{{ route('shipments.show', $log->entity_id) }}" class="cell-link">{{ $log->entity_label }}</a>
                                    @else
                                        <strong>{{ $log->entity_label ?: '#'.$log->entity_id }}</strong>
                                    @endif
                                </td>
                                <td class="audit-changes">
                                    @forelse (array_slice($lines, 0, 4) as $line) <div>{{ $line }}</div> @empty <span class="cell-sub">-</span> @endforelse
                                    @if (count($lines) > 4) <div class="cell-sub">+{{ count($lines) - 4 }} more</div> @endif
                                </td>
                                <td><span class="cell-sub">{{ $log->ip_address ?: '-' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $logs->links('vendor.pagination.cargotrace') }}
        @endif
    </div>
@endsection
