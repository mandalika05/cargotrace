{{--
 ============================================================
 BUAT APA : Daftar akun (Admin) dengan search dan filter peran.
 ============================================================
--}}
@extends('layouts.app')
@section('title', 'Users')

@section('actions')
    <a href="{{ route('users.create') }}" class="btn-primary"><x-icon name="plus" /> New user</a>
@endsection

@section('content')
    <form method="GET" action="{{ route('users.index') }}" class="card filter-bar">
        <div class="filter-grid">
            <div>
                <label for="q" class="field-label">Search</label>
                <input id="q" name="q" type="search" value="{{ $q }}" class="field-input" placeholder="Name or email">
            </div>
            <div>
                <label for="role" class="field-label">Role</label>
                <select id="role" name="role" class="field-input">
                    <option value="">All roles</option>
                    @foreach (['admin' => 'Administrator', 'operator' => 'Logistics Operator', 'customer' => 'Customer'] as $value => $label)
                        <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn-primary">Apply</button>
            <a href="{{ route('users.index') }}" class="btn-ghost">Reset</a>
        </div>
    </form>

    <div class="page-body">
        @if ($users->isEmpty())
            <div class="empty-state"><p class="empty-title">No users found</p></div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th class="col-right">Actions</th></tr></thead>
                    <tbody>
                        @foreach ($users as $account)
                            <tr>
                                <td><strong>{{ $account->name }}</strong></td>
                                <td>{{ $account->email }}</td>
                                <td><span class="chip {{ $account->role === 'admin' ? 'chip-delay' : ($account->role === 'operator' ? 'chip-transit' : 'chip-neutral') }}">{{ strtoupper($account->role) }}</span></td>
                                <td><div class="cell-actions"><a href="{{ route('users.edit', $account) }}" class="btn-ghost btn-sm">Edit</a></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $users->links('vendor.pagination.cargotrace') }}
        @endif
    </div>
@endsection
