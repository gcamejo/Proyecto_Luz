<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->limit(25)->get();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'nombre' => $notification->data['nombre'] ?? '',
                    'email' => $notification->data['email'] ?? '',
                    'fecha' => $notification->data['fecha'] ?? $notification->created_at->toIso8601String(),
                    'read_at' => $notification->read_at,
                ];
            })->values(),
        ]);
    }

    public function markRead(Request $request, $notificationId)
    {
        $notification = $request->user()->notifications()->where('id', $notificationId)->firstOrFail();
        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read.',
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }
}
