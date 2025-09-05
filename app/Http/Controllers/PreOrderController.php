<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PreOrder;
use App\Models\User;
use App\Models\Farm;
use App\Models\OrderDetail;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;



class PreOrderController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    // Vendor creates a pre-order
    public function store(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'vendor') {
            return response()->json(['success' => false, 'message' => 'Only vendors can create pre-orders.'], 403);
        }

        $data = $request->validate([
            'product_id'    => 'required|exists:products,id',
            'qty'           => 'required|integer|min:1',
            'delivery_date' => 'nullable|date',
            'location'      => 'nullable|string',
            'note_text'     => 'nullable|string',
        ]);

        $data['user_id'] = $user->id; // vendor

        try {
            DB::beginTransaction();

            // Create the pre-order
            $preOrder = PreOrder::create($data);

            // Notify all farmers
            $this->notificationService->notifyFarmers($preOrder);

            DB::commit();

            return response()->json(['success' => true, 'data' => $preOrder], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create pre-order and notifications.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    // Vendor confirms or rejects farm offer
    public function confirmOffer(Request $request, $orderDetailId)
    {
        $user = Auth::user();
        if ($user->role !== 'vendor') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:accept,reject',
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false,'errors'=>$validator->errors()],422);
        }

        $orderDetail = OrderDetail::findOrFail($orderDetailId);

        $orderDetail->offer_status = $request->action === 'accept' ? 'accepted' : 'rejected';
        $orderDetail->save();

        // Notify farm
        $this->notificationService->notifyFarm($orderDetail);
        // Update pre_order status based on confirmed offers
        $this->updatePreOrderStatus($orderDetail->pre_order_id);

        return response()->json(['success'=>true, 'message'=>'Offer processed']);
    }

    // Calculate pre-order status based on confirmed offers
    public function updatePreOrderStatus($preOrderId)
    {
        $preOrder = PreOrder::findOrFail($preOrderId);
        $totalRequested = $preOrder->qty;

        $totalConfirmed = OrderDetail::where('pre_order_id', $preOrderId)
            ->where('offer_status', 'accepted')
            ->sum('fulfilled_qty');

        if ($totalConfirmed == 0) {
            $status = 'pending';
        } elseif ($totalConfirmed < $totalRequested) {
            $status = 'partially_fulfilled';
        } else {
            $status = 'fulfilled';
        }

        $preOrder->status = $status;
        $preOrder->save();
    }
        /**
     * List order details for the authenticated farmer
     */
    public function index(Request $request) 
    {
        $user = auth()->user(); // farmer

        $preOrders = PreOrder::with(['user', 'product'])
            ->where('status', 'pending')
            ->whereDoesntHave('orderDetails', function ($query) use ($user) {
                $query->where('farm_id', $user->farm_id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(5); 

        // Transform the paginated data
        $data = $preOrders->getCollection()->map(function ($preOrder) {
            return [
                'pre_order_id' => $preOrder->id,
                'vendor_name'  => $preOrder->user->first_name . ' ' . $preOrder->user->last_name,
                'product_name' => $preOrder->product->name,
                'quantity'     => $preOrder->qty,
                'location'     => $preOrder->location,
                'note'         => $preOrder->note ?? 'No notes',
                'delivery_date'=> $preOrder->delivery_date->format('Y-m-d'),
                'status'       => ucfirst($preOrder->status),  
            ];
        })->values();

        return response()->json([
            'success' => true,
            'meta' => [
                'current_page' => $preOrders->currentPage(),
                'last_page'    => $preOrders->lastPage(),
                'per_page'     => $preOrders->perPage(),
                'total'        => $preOrders->total(),
            ],
            'data' => $data,
        ], 200);
    }


    public function destroy($preOrderId){
        $preOrder = PreOrder::findOrFail($preOrderId);
        $preOrder->delete();
        

    }
}
