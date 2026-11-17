<?php
/*
 * ============================================================
 * BUAT APA : Halaman notifikasi in-app (web): daftar, tandai dibaca, tandai semua dibaca.
 * HAK AKSES: semua peran yang login; setiap orang hanya melihat notifikasinya sendiri
 *            (query lewat $request->user()->notifications, jadi tidak bisa membuka punya orang lain).
 * ============================================================
 */

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    // GET /notifications?filter=all|unread
    public function index(Request $request)
    {
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';
        $user = $request->user();

        $query = $filter === 'unread' ? $user->unreadNotifications() : $user->notifications();

        return view('notifications.index', [
            'notifications' => $query->paginate(15)->withQueryString(),
            'filter' => $filter,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    // POST /notifications/{id}/read : tandai dibaca lalu buka shipment terkait
    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $url = (string) ($notification->data['url'] ?? '');
        // Hanya boleh ke alamat lokal (diawali "/" tetapi bukan "//") agar tidak jadi open redirect
        $safe = Str::startsWith($url, '/') && ! Str::startsWith($url, '//');

        return $safe ? redirect($url) : redirect()->route('notifications.index');
    }

    // POST /notifications/read-all
    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}
