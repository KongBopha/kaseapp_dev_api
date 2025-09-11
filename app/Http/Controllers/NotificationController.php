<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Constants\NotificationTypeEnum;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    // Fetch notifications
    public function index()
    {
        $user = Auth::user();

        if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Please log in again.',
        ], 401);
        }
        $query = Notification::with([
            'preOrder.product:id,name',
            'vendor:id,name',
            'farm:id,name',
            'reference:id,fulfilled_qty,offer_status',
        ])->where('recipient_id', $user->id);

        // Role-based filtering
        if ($user->role === 'vendor') {
            $query->whereIn('type', [
                NotificationTypeEnum::ACCEPTANCE->value,
                NotificationTypeEnum::REJECTION->value,
                NotificationTypeEnum::OFFER->value,
            ]);
        } elseif ($user->role === 'farmer') {
            $query->whereIn('type', [
                NotificationTypeEnum::PRE_ORDER->value,
                NotificationTypeEnum::ACCEPTANCE->value,
                NotificationTypeEnum::REJECTION->value,
            ]);
        } elseif($user->role==='consumer') {
            return response()->json([
                'success' => true,
                'data'    => [],
            ]);
        }

        $notifications = $query->orderBy('created_at', 'desc')->get();

        // Group notifications by pre_order and farm/vendor
        $grouped = $notifications
            ->groupBy(fn($item) => ($item->pre_order_id ?? 'none') . '_' . ($item->farm_id ?? $item->vendor_id ?? 'none'))
            ->map(fn($items) => [
                'pre_order_id'  => $items->first()->pre_order_id,
                'product'       => $items->first()->preOrder->product ?? null,
                'vendor'        => $items->first()->vendor,
                'farm'          => $items->first()->farm,
                'notifications' => $items->map(fn($n) => [
                    'id'          => $n->id,
                    'type'        => $n->type,
                    'message'     => $n->message,
                    'read_status' => $n->read_status,
                    'reference'   => $n->reference,
                    'created_at'  => $n->created_at,
                ])->values(),
            ])->values();

        return response()->json([
            'success' => true,
            'data'    => $grouped,
        ]);
    }

    public function unreadCount()
    {
        $user = Auth::user();

        $count = Notification::where('recipient_id', $user->id)
            ->where('read_status', false)
            ->count();

        return response()->json([
            'success' => true,
            'unread_count' => $count,
        ]);
    }

    public function markRead($id)
    {
        $notification = Notification::findOrFail($id);
        $this->notificationService->markAsRead($notification);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }
}
