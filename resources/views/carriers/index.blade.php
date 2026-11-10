{{--
 ============================================================
 BUAT APA : Daftar carrier (Admin). Ada search, status aktif, jumlah shipment,
            dan tombol Edit/Delete. Tabel bisa digeser ke samping di HP.
 ============================================================
--}}
@extends('layouts.app')
@section('title', 'Carriers')

@section('actions')
    <a href="{{ route('carriers.create') }}" class="btn-primary"><x-icon name="plus" /> New carrier</a>
@endsection

@section('content')
    <form method="GET" action="{{ route('carriers.index') }}" class="card filter-bar">
        <div class="filter-grid">
            <div class="field-full">
                <label for="q" class="field-label">Search</label>
                <input id="q" name="q" type="search" value="{{ $q }}" class="field-input" placeholder="Name or code">
            </div>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn-primary">Search</button>
            <a href="{{ route('carriers.index') }}" class="btn-ghost">Reset</a>
        </div>
    </form>

    <div class="page-body">
        @if ($carriers->isEmpty())
            <div class="empty-state"><p class="empty-title">No carriers found</p></div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Code</th><th>Contact</th><th>Status</th><th>Shipments</th><th class="col-right">Actions</th></tr></thead>
                    <tbody>
                        @foreach ($carriers as $carrier)
                            <tr>
                                <td><strong>{{ $carrier->name }}</strong></td>
                                <td>{{ $carrier->code }}</td>
                                <td>{{ $carrier->contact_email ?? '-' }}</td>
                                <td><span class="chip {{ $carrier->is_active ? 'chip-delivered' : 'chip-cancelled' }}">{{ $carrier->is_active ? 'ACTIVE' : 'INACTIVE' }}</span></td>
                                <td>{{ $carrier->shipments_count }}</td>
                                <td>
                                    <div class="cell-actions">
                                        <a href="{{ route('carriers.edit', $carrier) }}" class="btn-ghost btn-sm">Edit</a>
                                        <form method="POST" action="{{ route('carriers.destroy', $carrier) }}" onsubmit="return confirm('Delete this carrier?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn-danger btn-sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $carriers->links('vendor.pagination.cargotrace') }}
        @endif
    </div>
@endsection
