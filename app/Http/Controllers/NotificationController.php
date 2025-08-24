<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notification;
use App\Services\NotificationService;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    // List all notifications for a user
    public function index($userId)
    {
        $notifications = Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notifications);
    }

    // Mark a notification as read
    public function markRead($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->update(['read_status' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'data' => $notification
        ]);
    }

    public function show($id){
        $notification = Notification::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $notification
        ]);
    }
}
     