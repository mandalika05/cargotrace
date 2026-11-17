{{--
 ============================================================
 BUAT APA : Halaman notifikasi in-app (P13.3). Semua peran melihat notifikasinya sendiri.
 FITUR    : tab Semua / Belum dibaca, klik item = tandai dibaca lalu buka shipment,
            tombol "Mark all read", pagination server-side (15 per halaman).
 DATA     : $notifications (paginator), $filter (all|unread), $unreadCount.
 ============================================================
--}}
@extends('layouts.app')
@section('title', 'Notifications')

@section('actions')
    @if ($unreadCount > 0)
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit" class="btn-ghost"><x-icon name="check-circle" /> Mark all read</button>
        </form>
    @endif
@endsection

@section('content')
    <div class="notif-tabs">
        <a href="{{ route('notifications.index') }}" class="{{ $filter === 'all' ? 'btn-primary' : 'btn-ghost' }}">All</a>
        <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" class="{{ $filter === 'unread' ? 'btn-primary' : 'btn-ghost' }}">Unread ({{ $unreadCount }})</a>
    </div>

    @if ($notifications->isEmpty())
        <div class="empty-state">
            <p class="empty-title">{{ $filter === 'unread' ? 'You are all caught up' : 'No notifications yet' }}</p>
            <p>Updates about your shipments (status changes, ETA changes and exceptions) will appear here.</p>
        </div>
    @else
        <div class="notif-list">
            @foreach ($notifications as $n)
                <form method="POST" action="{{ route('notifications.read', $n->id) }}">
                    @csrf
                    <button type="submit" class="notif-row {{ $n->read_at ? '' : 'is-unread' }}">
                        <span class="bell-dot bell-dot-{{ $n->data['level'] ?? 'info' }}"></span>
                        <span>
                            <span class="notif-title">{{ $n->data['title'] ?? 'Notification' }}</span><br>
                            <span class="notif-msg">{{ $n->data['message'] ?? '' }}</span>
                            <span class="notif-time" style="display:block">{{ $n->created_at->format('d M Y, H:i') }} &middot; {{ $n->created_at->diffForHumans() }}</span>
                        </span>
                    </button>
                </form>
            @endforeach
        </div>

        {{ $notifications->links('vendor.pagination.cargotrace') }}
    @endif
@endsection
