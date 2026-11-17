{{--
 ============================================================
 BUAT APA : Daftar menu navigasi, dipakai di sidebar (laptop) DAN menu hamburger (HP),
            supaya menu hanya ditulis satu kali.
 MENU PER PERAN:
   semua peran : Dashboard, Shipments (customer: "My shipments")
   staf         : Needs attention
   semua peran  : Exceptions (customer: "My exceptions")
   semua peran  : Notifications (P13.3)
   admin saja  : Carriers, Locations, Users, Audit log (P13.3), Settings
 Menu aktif diberi kelas nav-link-active (warna + garis kuningan).
 Ikon: <x-icon name="..." /> (lihat components/icon.blade.php).
 Menu baru di tahap berikutnya (Exceptions, Audit log, dst) ditambah di sini.
 ============================================================
--}}
@php
    $navUser = auth()->user();
    $navClass = fn (string $pattern) => 'nav-link'.(request()->routeIs($pattern) ? ' nav-link-active' : '');
@endphp

<div class="nav-label">Overview</div>
<a href="{{ route('dashboard') }}" class="{{ $navClass('dashboard') }}"><x-icon name="grid" /> Dashboard</a>
<div class="nav-label">Operations</div>
@unless ($navUser->isCustomer())
    <a href="{{ route('attention.index') }}" class="{{ $navClass('attention.*') }}"><x-icon name="flag" /> Needs attention</a>
@endunless
<a href="{{ route('shipments.index') }}" class="{{ $navClass('shipments.*') }}"><x-icon name="package" /> {{ $navUser->isCustomer() ? 'My shipments' : 'Shipments' }}</a>
<a href="{{ route('exceptions.index') }}" class="{{ $navClass('exceptions.*') }}"><x-icon name="alert-triangle" /> {{ $navUser->isCustomer() ? 'My exceptions' : 'Exceptions' }}</a>
<a href="{{ route('notifications.index') }}" class="{{ $navClass('notifications.*') }}"><x-icon name="bell" /> Notifications</a>

@if ($navUser->isAdmin())
    <div class="nav-label">Administration</div>
    <a href="{{ route('carriers.index') }}" class="{{ $navClass('carriers.*') }}"><x-icon name="truck" /> Carriers</a>
    <a href="{{ route('locations.index') }}" class="{{ $navClass('locations.*') }}"><x-icon name="map-pin" /> Locations</a>
    <a href="{{ route('users.index') }}" class="{{ $navClass('users.*') }}"><x-icon name="users" /> Users</a>
    <a href="{{ route('audit-logs.index') }}" class="{{ $navClass('audit-logs.*') }}"><x-icon name="file-text" /> Audit log</a>
    <a href="{{ route('settings.edit') }}" class="{{ $navClass('settings.*') }}"><x-icon name="sliders" /> Settings</a>
@endif
