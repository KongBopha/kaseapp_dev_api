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
 * 🛒 Vendor confirms or rejects an offer
 */
public function confirmOffer(Request $request, $id)
{
    $user = Auth::user();

    // 🔐 Only vendors can confirm or reject offers
    if ($user->role !== 'vendor') {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    // 🧾 Load order detail with relationships
    $orderDetail = OrderDetail::with('preOrder.product')->findOrFail($id);

    // ❌ Prevent vendor from confirming another vendor’s pre-order
    if ($orderDetail->preOrder->user_id !== $user->id) {
        return response()->json(['success' => false, 'message' => 'Not your pre-order'], 403);
    }

    // ⛔ Prevent multiple confirmation attempts
    if (in_array($orderDetail->offer_status, ['confirmed', 'rejected'])) {
        return response()->json(['success' => false, 'message' => 'Offer already handled'], 400);
    }

    // 📝 Validate request input
    $data = $request->validate([
        'offer_status' => 'required|in:confirmed,rejected',
    ]);

    try {
        DB::beginTransaction();

        // 🪄 Update offer status
        $orderDetail->update($data);
        $orderDetail->refresh()->load('preOrder.product');

        if ($data['offer_status'] === 'confirmed') {

            // 🧭 Extract core data
            $product           = $orderDetail->preOrder->product;
            $productId         = $product->id;
            $farmId            = $orderDetail->farm_id;
            $farmOfferQty      = floatval($orderDetail->fulfilled_qty);  // Farm’s total supply
            $vendorRequestQty  = floatval($orderDetail->preOrder->qty);

            // 🧮 Calculate quantity distribution
            $vendorQty  = min($farmOfferQty, $vendorRequestQty);
            $surplusQty = max(0, $farmOfferQty - $vendorRequestQty);

            // 🌾 Calculate harvest date
            $harvestedDate = $this->calculateHarvestDate($product->name);

            // 🌱 Step 1: Create crop record (represents the full farm supply)
            $crop = Crop::create([
                'farm_id'       => $farmId,
                'product_id'    => $productId,
                'name'          => $product->name . ' Crop',
                'qty'           => $farmOfferQty,
                'image'         => $product->image ?? null,
                'status'        => 0, // 0 = planting
                'harvest_date'  => $harvestedDate,
            ]);

            // 🔗 Step 2: Link crop to the order detail
            $orderDetail->update([
                'crop_id'       => $crop->id,
                'fulfilled_qty' => $vendorQty,
            ]);

            // 📦 Step 3: Create MarketSupply if surplus exists
            if ($surplusQty > 0) {
                MarketSupply::create([
                    'farm_id'        => $farmId,
                    'crop_id'        => $crop->id,
                    'product_id'     => $productId,
                    'available_qty'  => $surplusQty,
                    'unit'           => $orderDetail->unit ?? 'kg',
                    'availability'   => $harvestedDate,
                ]);
            }

            // 🪄 Step 4: Update pre-order status & notify farm
            $preOrderStatus = $this->preOrderService->updatePreOrderStatus($orderDetail->pre_order_id);
            if (!$preOrderStatus) {
                throw new \Exception("Failed to update status in pre-order");
            }

            $notification = $this->notificationService->notifyFarm($orderDetail);
            if (!$notification) {
                throw new \Exception("Failed to create notification for farmer.");
            }
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => "Offer {$data['offer_status']} successfully",
            'data'    => $orderDetail,
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}




/**
 * Calculate estimated harvest date based on product type
 */
private function calculateHarvestDate(string $productName): \Carbon\Carbon
{
    $productName = strtolower($productName);

    return match (true) {
        str_contains($productName, 'tomato') => now()->addDays(75),
        str_contains($productName, 'cherry') => now()->addDays(65),
        str_contains($productName, 'cucumber') => now()->addDays(55),
        str_contains($productName, 'eggplant') => now()->addDays(85),
        str_contains($productName, 'corn') => now()->addDays(90),
        str_contains($productName, 'carrot') => now()->addDays(80),
        default => now()->addDays(60), // fallback for other crops
    };
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
