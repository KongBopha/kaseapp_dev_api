<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PreOrder;
use App\Models\Farm;
use App\Models\OrderDetail;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\PreOrderRequest;

class PreOrderController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    // GET all pre-orders with pagination
    public function index()
    {
        $preOrders = PreOrder::with('product')->paginate(10);

        $preOrders->getCollection()->transform(function ($order) {
            $order->product_name = $order->product_id
                ? $order->product->name
                : $order->input_product_name;
            return $order;
        });

        return response()->json([
            'success' => true,
            'data' => $preOrders->items(),
            'meta' => [
                'current_page' => $preOrders->currentPage(),
                'last_page' => $preOrders->lastPage(),
                'total' => $preOrders->total()
            ]
        ]);
    }

    // GET single pre-order
    public function show($id)
    {
        $preOrder = PreOrder::with('product')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $preOrder]);
    }

    // CREATE pre-order (vendor only)
    public function store(PreOrderRequest $request)
    {
        $user = Auth::user();
        if ($user->role !== 'vendor') {
            return response()->json(['success'=>false,'message'=>'Only vendors can create pre-orders.'],403);
        }

        $validated = $request->validated();

        if (!$validated['product_id'] && empty($validated['input_product_name'])) {
            return response()->json(['success'=>false,'message'=>'Please select a product or enter a custom product name.'],422);
        }

        $preOrder = PreOrder::create($validated);

        // Notify all farms
        $farms = Farm::all();
        $farmUserIds = $farms->pluck('owner_id')->toArray();

        $this->notificationService->sendToUsers(
            $request->user_id,
            $farmUserIds,
            'pre_order',
            "New pre-order request: {$request->qty} units of product ID {$request->product_id}",
            $preOrder->id
        );

        return response()->json(['success'=>true,'message'=>'Pre-order created successfully','data'=>$preOrder],201);
    }

    // UPDATE pre-order
    public function update(Request $request, $id)
    {
        $preOrder = PreOrder::findOrFail($id);
        if ($preOrder->status !== 'pending') {
            return response()->json(['success'=>false,'message'=>'Only pending pre-orders can be updated'],403);
        }

        $validated = $request->validate([
            'user_id' => 'sometimes|integer',
            'product_id' => 'sometimes|integer',
            'qty' => 'sometimes|numeric|min:0.01',
            'location' => 'sometimes|string',
            'note_text' => 'nullable|string',
            'delivery_date' => 'sometimes|date',
            'recurring_schedule' => 'nullable|string',
            'input_product_name' => 'nullable|string|max:255'
        ]);

        $preOrder->update($validated);
        return response()->json(['success'=>true,'message'=>'Pre-order updated','data'=>$preOrder]);
    }

    // DELETE pre-order
    public function destroy($id)
    {
        $preOrder = PreOrder::findOrFail($id);
        if ($preOrder->status !== 'pending') {
            return response()->json(['success'=>false,'message'=>'Only pending pre-orders can be deleted'],403);
        }

        $preOrder->delete();
        return response()->json(['success'=>true,'message'=>'Pre-order deleted'],204);
    }

    // GET pre-orders by user
    public function getByUser($user_id)
    {
        $preOrders = PreOrder::where('user_id',$user_id)->with('product')->get();
        return response()->json(['success'=>true,'data'=>$preOrders]);
    }

    // Update pre-order status based on confirmed offers
    public function updatePreOrderStatus($preOrderId)
    {
        $preOrder = PreOrder::findOrFail($preOrderId);
        $totalRequested = $preOrder->qty;
        $totalConfirmed = OrderDetail::where('pre_order_id',$preOrderId)
            ->where('offer_status','accepted')
            ->sum('fulfilled_qty');

        if ($totalConfirmed == 0) $status = 'pending';
        elseif ($totalConfirmed < $totalRequested) $status = 'partially_fulfilled';
        else $status = 'fulfilled';

        $preOrder->update(['status' => $status]);
    }
}
