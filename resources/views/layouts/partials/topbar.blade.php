{{--
 ============================================================
 BUAT APA : Bar atas di laptop (P13.3, dipercantik di P14.5): judul halaman, kotak cari shipment,
            lonceng notifikasi, dan profil. Sesuai mockup dosen. Di HP, lonceng ada di bar atas (lihat layouts/app.blade.php).
 DATA     : $unreadCount dan $recentNotifications dihitung di layouts/app.blade.php.
 LONCENG  : <details> (tanpa library). Menutup sendiri saat klik di luar / tekan Esc
            (resources/js/topbar.js). Tiap item adalah form POST "tandai dibaca" lalu
            membuka shipment terkait.
 ============================================================
--}}
<div class="topbar">
  <div class="topbar-inner">
    {{-- P14.5: judul halaman pindah ke bar atas (seperti mockup dosen). Judul asli di isi halaman tetap ada (disembunyikan di laptop) untuk pembaca layar. --}}
    <div class="topbar-title">@yield('title', 'Dashboard')</div>

    <form method="GET" action="{{ route('shipments.index') }}" class="topbar-search" role="search">
        <x-icon name="search" />
        <input type="search" name="q" class="topbar-search-input" placeholder="Search shipment number..." aria-label="Search shipments" maxlength="100">
    </form>

    <details class="bell" id="bell-menu">
        <summary class="bell-button" aria-label="Notifications">
            <x-icon name="bell" class="icon-lg" />
            @if ($unreadCount > 0)
                <span class="bell-badge">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
            @endif
        </summary>
        <div class="bell-panel">
            <div class="bell-head">
                <strong>Notifications</strong>
                @if ($unreadCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="bell-link">Mark all read</button>
                    </form>
                @endif
            </div>

            @forelse ($recentNotifications as $n)
                <form method="POST" action="{{ route('notifications.read', $n->id) }}">
                    @csrf
                    <button type="submit" class="bell-item {{ $n->read_at ? '' : 'is-unread' }}">
                        <span class="bell-dot bell-dot-{{ $n->data['level'] ?? 'info' }}"></span>
                        <span class="bell-text">
                            <span class="bell-title">{{ $n->data['title'] ?? 'Notification' }}</span>
                            <span class="bell-msg">{{ $n->data['message'] ?? '' }}</span>
                            <span class="bell-time">{{ $n->created_at->diffForHumans() }}</span>
                        </span>
                    </button>
                </form>
            @empty
                <div class="bell-empty">No notifications yet.</div>
            @endforelse

            <a href="{{ route('notifications.index') }}" class="bell-footer">View all notifications</a>
        </div>
    </details>

    {{-- P14.5: profil singkat (nama + peran) di kanan bar atas --}}
    <div class="topbar-profile" title="{{ $user->email }}">
        <span class="avatar">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
        <div class="profile-text">
            <div class="profile-name">{{ $user->name }}</div>
            <span class="profile-role">{{ $user->role }}</span>
        </div>
    </div>
  </div>
</div>
