<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use App\Models\Notification;
use App\Models\Vendor;
use App\Models\Product;
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
            'preOrder.product:id,name,image',
            'preOrder.user:id,first_name,last_name,email,phone',
            'farm:id,name',
            'reference:id,fulfilled_qty,offer_status',
        ])->where('recipient_id', $user->id);

        //  Role-based filtering logic
        switch ($user->role) {
            case 'vendor':
                $query->whereIn('type', [
                    NotificationTypeEnum::ACCEPTANCE->value,
                    NotificationTypeEnum::REJECTION->value,
                    NotificationTypeEnum::OFFER->value,
                ]);
                break;

            case 'farmer':
                $query->whereIn('type', [
                    NotificationTypeEnum::PRE_ORDER->value,
                    NotificationTypeEnum::ACCEPTANCE->value,
                    NotificationTypeEnum::REJECTION->value,
                ]);
                break;

            case 'consumer':
                $query->whereIn('type', [
                    NotificationTypeEnum::ACCEPTANCE->value,
                    NotificationTypeEnum::REJECTION->value,
                ]);
                break;

            default:
                $query->whereIn('type', [
                    NotificationTypeEnum::ACCEPTANCE->value,
                    NotificationTypeEnum::REJECTION->value,
                ]);
                break;
        }

        $notifications = $query->orderBy('created_at', 'desc')->get();

        // Group notifications (based on pre_order or role-based ones)
        $grouped = $notifications->groupBy(function ($item) {
            // Role upgrade notifications don’t have pre_order_id
            if (in_array($item->type, ['acceptance', 'rejection'])) {
                return 'role_upgrade_' . $item->id;
            }
            return ($item->pre_order_id ?? 'none') . '_' . ($item->farm_id ?? $item->vendor_id ?? 'none');
        })
        ->map(function ($items) {
            $first = $items->first();

            $product = $first->preOrder->product ?? ($first->reference?->product ?? null);

            if (!$product && isset($first->product_id)) {
                $product = Product::find($first->product_id);
            }

            // Handle role upgrade notifications (no pre_order)
            if (in_array($first->type, ['approved', 'rejected'])) {
                return [
                    'category'      => 'role_upgrade',
                    'notifications' => $items->map(fn($n) => [
                        'id'          => $n->id,
                        'type'        => $n->type,
                        'message'     => $n->message,
                        'read_status' => $n->read_status,
                        'created_at'  => $n->created_at,
                    ])->values(),
                ];
            }

            // Normal grouped notifications
            return [
                'pre_order_id'  => $first->pre_order_id,
                'product'       => $product
                    ? [
                        'name'  => $product->name,
                        'image' => $product->image,
                    ]
                    : null,
                'vendor' => [
                    'user_info' => $first->preOrder->user
                        ? [
                            'name'  => trim(($first->preOrder->user->first_name ?? '') . ' ' . ($first->preOrder->user->last_name ?? '')),
                            'phone' => $first->preOrder->user->phone ?? null,
                        ]
                        : null,
                    'vendor_info' => $first->preOrder->user
                        ? Vendor::where('owner_id', $first->preOrder->user->id)->first(['id', 'name', 'address'])
                        : null,
                ],
                'farm'          => $first->farm,
                'notifications' => $items->map(fn($n) => [
                    'id'          => $n->id,
                    'type'        => $n->type,
                    'message'     => $n->message,
                    'read_status' => $n->read_status,
                    'reference'   => $n->reference,
                    'product'     => $product,
                    'created_at'  => $n->created_at,
                ])->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $grouped,
        ]);
    }

    public function unreadCount()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please log in again.',
            ], 401);
        }

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
