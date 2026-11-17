<?php
/*
 * ============================================================
 * BUAT APA : REST API notifikasi in-app milik user yang login.
 * ENDPOINT : GET /notifications?filter=unread&per_page=  (+ meta unread_count)
 *            POST /notifications/{id}/read   |   POST /notifications/read-all
 * AMAN     : hanya notifikasi milik sendiri; id orang lain -> 404.
 * ============================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);
        $query = $request->query('filter') === 'unread' ? $user->unreadNotifications() : $user->notifications();

        return NotificationResource::collection($query->paginate($perPage)->withQueryString())
            ->additional(['unread_count' => $user->unreadNotifications()->count()]);
    }

    public function read(Request $request, string $id): NotificationResource
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return new NotificationResource($notification->fresh());
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All notifications marked as read.', 'unread_count' => 0]);
    }
}
