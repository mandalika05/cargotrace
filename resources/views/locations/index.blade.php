{{--
 ============================================================
 BUAT APA : Daftar lokasi (Admin): nama, kota, negara, koordinat.
 ============================================================
--}}
@extends('layouts.app')
@section('title', 'Locations')

@section('actions')
    <a href="{{ route('locations.create') }}" class="btn-primary"><x-icon name="plus" /> New location</a>
@endsection

@section('content')
    <form method="GET" action="{{ route('locations.index') }}" class="card filter-bar">
        <div class="filter-grid">
            <div class="field-full">
                <label for="q" class="field-label">Search</label>
                <input id="q" name="q" type="search" value="{{ $q }}" class="field-input" placeholder="Name, city or country">
            </div>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn-primary">Search</button>
            <a href="{{ route('locations.index') }}" class="btn-ghost">Reset</a>
        </div>
    </form>

    <div class="page-body">
        @if ($locations->isEmpty())
            <div class="empty-state"><p class="empty-title">No locations found</p></div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>City</th><th>Country</th><th>Coordinates</th><th class="col-right">Actions</th></tr></thead>
                    <tbody>
                        @foreach ($locations as $location)
                            <tr>
                                <td><strong>{{ $location->name }}</strong></td>
                                <td>{{ $location->city }}</td>
                                <td>{{ $location->country }}</td>
                                <td>{{ $location->latitude !== null ? $location->latitude.', '.$location->longitude : '-' }}</td>
                                <td>
                                    <div class="cell-actions">
                                        <a href="{{ route('locations.edit', $location) }}" class="btn-ghost btn-sm">Edit</a>
                                        <form method="POST" action="{{ route('locations.destroy', $location) }}" onsubmit="return confirm('Delete this location?')">
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
            {{ $locations->links('vendor.pagination.cargotrace') }}
        @endif
    </div>
@endsection
