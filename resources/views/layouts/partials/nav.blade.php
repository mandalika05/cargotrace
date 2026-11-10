{{--
 ============================================================
 BUAT APA : Daftar menu navigasi, dipakai di sidebar (laptop) DAN menu hamburger (HP),
            supaya menu hanya ditulis satu kali.
 MENU PER PERAN:
   semua peran : Dashboard, Shipments (customer: "My shipments")
   admin saja  : Carriers, Locations, Users
 Menu aktif diberi kelas nav-link-active (warna + garis kuningan).
 Ikon: <x-icon name="..." /> (lihat components/icon.blade.php).
 Menu baru di tahap berikutnya (Exceptions, Audit log, dst) ditambah di sini.
 ============================================================
--}}
@php
    $navUser = auth()->user();
    $navClass = fn (string $pattern) => 'nav-link'.(request()->routeIs($pattern) ? ' nav-link-active' : '');
@endphp

<a href="{{ route('dashboard') }}" class="{{ $navClass('dashboard') }}"><x-icon name="grid" /> Dashboard</a>
<a href="{{ route('shipments.index') }}" class="{{ $navClass('shipments.*') }}"><x-icon name="package" /> {{ $navUser->isCustomer() ? 'My shipments' : 'Shipments' }}</a>

@if ($navUser->isAdmin())
    <a href="{{ route('carriers.index') }}" class="{{ $navClass('carriers.*') }}"><x-icon name="truck" /> Carriers</a>
    <a href="{{ route('locations.index') }}" class="{{ $navClass('locations.*') }}"><x-icon name="map-pin" /> Locations</a>
    <a href="{{ route('users.index') }}" class="{{ $navClass('users.*') }}"><x-icon name="users" /> Users</a>
@endif
