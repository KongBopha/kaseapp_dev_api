<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderDetail;
use App\Models\PreOrder;
use App\Models\User;
use App\Models\Crop;
use App\Models\Farm;
use App\Models\MarketSupply;
use App\Services\NotificationService;
use App\Services\PreOrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; 


class OrderDetailsController extends Controller
{
    protected $notificationService;
    protected $preOrderService;

    public function __construct(NotificationService $notificationService,PreOrderService $preOrderService)
    {
        $this->notificationService = $notificationService;
        $this->preOrderService = $preOrderService;

    }

    /**
     * Farmer submits an offer
     */
    public function store(Request $request, $preOrderId)
    {
        $user = Auth::user();
        if ($user->role !== 'farmer') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $preOrder = PreOrder::findOrFail($preOrderId);
        $farm = $user->farms()->first();

        if (!$farm) {
            return response()->json(['success' => false, 'message' => 'You do not own a farm'], 403);
        }

        if (OrderDetail::where('pre_order_id', $preOrder->id)->where('farm_id', $farm->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'You have already offered.'], 400);
        }

        $data = $request->validate([
            'fulfilled_qty' => 'required|numeric|min:0.01',
            'offer_status'  => 'required|in:accepted,rejected',
            'description'   => 'nullable|string',
        ]);

        $data['pre_order_id'] = $preOrder->id;
        $data['farm_id'] = $farm->id;

        try {
            DB::beginTransaction();

            //  Create the offer
            $orderDetail = OrderDetail::create($data);

            // Notify the vendor (recipient_id = vendor's user ID)
            $notification = $this->notificationService->notifyVendor($orderDetail);
            if (!$notification) {
                throw new \Exception("Failed to create notification for vendor.");
            }

            DB::commit();

            return response()->json(['success' => true, 'data' => $orderDetail]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Vendor confirms or rejects an offer
     */
    public function confirmOffer(Request $request, $id)
    {
        $user = Auth::user();
        if ($user->role !== 'vendor') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $orderDetail = OrderDetail::with('preOrder')->findOrFail($id);

        if ($orderDetail->preOrder->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Not your pre-order'], 403);
        }

        if (in_array($orderDetail->offer_status, ['confirmed', 'rejected'])) {
            return response()->json(['success' => false, 'message' => 'Offer already handled'], 400);
        }

        $data = $request->validate([
            'offer_status' => 'required|in:confirmed,rejected',
        ]);

        try {
            DB::beginTransaction();

            // Update the offer
            $orderDetail->update($data);

            // create market supply after vendor confirm
            if($data['offer_status']==='confirmed'){
            
            $productId = $orderDetail->preOrder->product_id;

                // assign crop simulation
            $crop = Crop::where('farm_id',$orderDetail->farm_id)
                        ->where('product_id', $productId)
                        ->where('status', 0)  
                        ->first();
                if(!$crop){
            throw new \Exception("No available crop for this farm and product.");
            }
                // 2. Update order_detail with the crop_id
            $orderDetail->crop_id = $crop->id;
            $orderDetail->save();

                $marketSupply = MarketSupply::firstOrCreate(
                    [
                        'farm_id'   =>$orderDetail->farm_id,
                        'crop_id'   =>$crop->id ,
                        'product_id'=>$productId
                    ],  
                    [
                        'available_qty'     =>    0,
                        'unit'              => $crop->unit ?? 'kg',
                        'availability'      => $crop->harvest_date,
                    ]
                );

                //calculate remaining quantity
                $remainingQty =  $crop->qty - $orderDetail->fulfilled_qty;
                $marketSupply->available_qty= max(0, $remainingQty);
                $marketSupply->save();
            }

            // Update pre-order status based on all confirmed offers
            $prorder_status =$this->preOrderService->updatePreOrderStatus($orderDetail->pre_order_id);
            if(!$prorder_status){
                throw new \Exception("Failed to update status in pre order");

            }
            //  Notify the farmer (recipient_id = farmer's user ID)
            $notification = $this->notificationService->notifyFarm($orderDetail);
            if (!$notification) {
                throw new \Exception("Failed to create notification for farmer.");
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Offer {$data['offer_status']} successfully",
                'data'    => $orderDetail
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * List order details for the authenticated vendor view
     */
    public function index(Request $request)
    {
        $vendor = auth()->user(); // vendor

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please log in again.',
            ], 401);
        }

        if(!$vendor||$vendor->role!=='vendor'){
            return response()->json([
                'success'=>true,
                'meta'=>[ 
                    'current_page'=>1,
                    'last_page'    => 1,
                    'per_page'     => 0,
                    'total'        => 0,

                ],
                'data'=>[],
            ], 200);
        }
        
        $orderDetails = OrderDetail::with(['farm', 'preOrder.product'])
            ->where('offer_status', 'accepted')
            ->whereHas('preOrder', function ($query) use ($vendor) {
                $query->where('user_id', $vendor->id); // only this vendor's pre-orders
            })
            ->orderBy('created_at', 'desc')
            ->paginate(5);

        $data = $orderDetails->getCollection()->map(function ($orderDetail) {
            $preOrder = $orderDetail->preOrder;
            return [
                'order_detail_id' => $orderDetail->id, 
                'pre_order_id' => $preOrder->id,
                'user_id'         => $preOrder->user_id, 
                'farm_name'  => $orderDetail->farm->name ?? 'Unknown', 
                'product_name' => $preOrder->product->name,
                'requested_qty'=> $preOrder->qty,
                'fulfilled_qty'=> $orderDetail->fulfilled_qty,
                'location'     => $preOrder->location,
                'note'         => $preOrder->note ?? 'No notes',
                'delivery_date'=> $preOrder->delivery_date->format('Y-m-d'),
                'offer_status' => $orderDetail->offer_status,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'meta' => [
                'current_page' => $orderDetails->currentPage(),
                'last_page'    => $orderDetails->lastPage(),
                'per_page'     => $orderDetails->perPage(),
                'total'        => $orderDetails->total(),
            ],
            'data' => $data,
        ]);
    }   
public function filterOrderDetails(Request $request)
{
    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized',
        ], 403);
    }

    // Define allowed offer_status for each role
    $allowedStatuses = [
        'farmer' => ['pending','accepted', 'rejected', 'confirmed'],
        'vendor' => ['pending','accepted', 'confirmed', 'rejected'],
    ];

    $role = $user->role;

    if (!isset($allowedStatuses[$role])) {
        return response()->json([
            'success' => false,
            'message' => 'Role not supported',
        ], 403);
    }

    $inputStatus = $request->input('offer_status');

    // Validate status
    if ($inputStatus && !in_array($inputStatus, $allowedStatuses[$role])) {
        return response()->json([
            'success' => false,
            'message' => "Invalid status for your role"
        ], 400);
    }

    // Build query
    $query = OrderDetail::with(['preOrder.product', 'farm.owner']); // load farmer's user

    if ($role === 'farmer') {
        $farm = $user->farms()->first();
        if (!$farm) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have a farm assigned.',
            ], 400);
        }
        $query->where('farm_id', $farm->id);
    } elseif ($role === 'vendor') {
        $query->whereHas('preOrder', function ($q) use ($user) {
            $q->where('user_id', $user->id); // only pre-orders created by this vendor
        });
    }

    if ($inputStatus) {
        $query->where('offer_status', $inputStatus);
    }

    // Paginate
    $orderDetails = $query->orderBy('created_at', 'desc')->paginate(5);

    // Map to response
    $data = $orderDetails->getCollection()->map(function ($orderDetail) use ($role) {
        $preOrder = $orderDetail->preOrder;
        $farm = $orderDetail->farm;
        $farmerUser = $farm->owner ?? null;
        return [
            'order_detail_id' => $orderDetail->id,
            'pre_order_id'   => $preOrder->id,
            'vendorName'     => $farm->name ?? 'Unknown',          // farm name
            'user_id'        => $farmerUser->id ?? 0,              // farmer's user ID
            'product_name'   => $preOrder->product->name,
            'requested_qty'  => $preOrder->qty,
            'fulfilled_qty'  => $orderDetail->fulfilled_qty,
            'location'       => $preOrder->location,
            'note'           => $preOrder->note ?? 'No notes',
            'delivery_date'  => $preOrder->delivery_date->format('Y-m-d'),
            'offer_status'   => $orderDetail->offer_status,
        ];
    })->values();

    return response()->json([
        'success' => true,
        'meta' => [
            'current_page' => $orderDetails->currentPage(),
            'last_page'    => $orderDetails->lastPage(),
            'per_page'     => $orderDetails->perPage(),
            'total'        => $orderDetails->total(),
        ],
        'data' => $data,
    ]);
}

}
