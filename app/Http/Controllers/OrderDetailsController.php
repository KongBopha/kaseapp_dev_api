<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderDetail;
use App\Models\PreOrder;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;

class OrderDetailsController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    // GET all order items
    public function index()
    {
        $orders = OrderDetail::with(['preorder','farm','product','crop'])->get();
        return response()->json(['success'=>true,'data'=>$orders]);
    }

    // GET single order item
    public function show($id)
    {
        $orderDetail = OrderDetail::with(['preorder','farm','product','crop'])->findOrFail($id);
        return response()->json(['success'=>true,'data'=>$orderDetail]);
    }

    // CREATE order item (farm offer)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'pre_order_id' => 'required|exists:pre_orders,id',
            'farm_id' => 'required|exists:farms,id',
            'product_id' => 'required|exists:products,id',
            'crop_id' => 'nullable|exists:crops,id',
            'fulfilled_qty' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
        ]);

        $orderItem = OrderDetail::create(array_merge($validated, ['offer_status'=>'pending']));

        $preOrder = PreOrder::findOrFail($request->pre_order_id);

        $this->notificationService->sendToUser(
            $request->farm_id,
            $preOrder->user_id,
            'offer',
            "Farm ID {$request->farm_id} offers {$request->fulfilled_qty} units for pre-order ID {$request->pre_order_id}",
            $request->pre_order_id,
            $orderItem->id
        );

        return response()->json(['success'=>true,'message'=>'Offer created and vendor notified','data'=>$orderItem],201);
    }

    // UPDATE order item
    public function update(Request $request, $id)
    {
        $orderDetail = OrderDetail::findOrFail($id);
        $validated = $request->validate([
            'fulfilled_qty' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'offer_status' => 'nullable|in:pending,accepted,rejected,confirmed,cancelled'
        ]);

        $orderDetail->update($validated);
        return response()->json(['success'=>true,'message'=>'Order item updated','data'=>$orderDetail]);
    }

    // DELETE order item
    public function destroy($id)
    {
        $orderDetail = OrderDetail::findOrFail($id);
        $orderDetail->delete();
        return response()->json(['success'=>true,'message'=>'Order item deleted']);
    }

    // Confirm offer by vendor
    public function confirmOffer(Request $request, $id)
    {
        $user = Auth::user();
        if ($user->role !== 'vendor') {
            return response()->json(['success' => false, 'message' => 'Only vendors can confirm offers.'], 403);
        }

        $orderItem = OrderDetail::findOrFail($id);
        $orderItem->update(['offer_status' => 'accepted']);

        // Update pre-order status
        app(\App\Http\Controllers\PreOrderController::class)
            ->updatePreOrderStatus($orderItem->pre_order_id);

        return response()->json([
            'success' => true,
            'message' => 'Offer confirmed by vendor, pre-order status updated',
            'data' => $orderItem
        ]);
    }

    // Cancel offer
    public function cancelOffer(Request $request, $id)
    {
        $user = Auth::user();
        if ($user->role !== 'vendor') {
            return response()->json(['success' => false, 'message' => 'Only vendors can cancel offers.'], 403);
        }

        $orderItem = OrderDetail::findOrFail($id);
        $orderItem->update(['offer_status' => 'cancelled']);

        $this->notificationService->sendToUser(
            $orderItem->farm_id,
            $orderItem->preorder->user_id,
            'offer_cancelled',
            "Offer ID {$orderItem->id} has been cancelled",
            $orderItem->pre_order_id,
            $orderItem->id
        );

        return response()->json(['success'=>true,'message'=>'Offer cancelled and notification sent','data'=>$orderItem]);
    }
}
