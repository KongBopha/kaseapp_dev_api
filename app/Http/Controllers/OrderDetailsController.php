<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderDetail;
use App\Models\PreOrder;
use App\Models\User;
use App\Models\Crop;
use App\Models\Vendor;
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
 * Farmer submits an offer to a vendor's pre-order
 */
public function store(Request $request, $preOrderId)
{
    $user = Auth::user();

    // Only farmers can submit offers
    if ($user->role !== 'farmer') {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 403);
    }

    $preOrder = PreOrder::findOrFail($preOrderId);
    $farm = $user->farms()->first();

    if (!$farm) {
        return response()->json([
            'success' => false,
            'message' => 'You do not own a farm'
        ], 403);
    }

    // Ensure this farmer submits only once per pre-order
    $alreadySubmitted = OrderDetail::where('pre_order_id', $preOrder->id)
        ->where('farm_id', $farm->id)
        ->exists();

    if ($alreadySubmitted) {
        return response()->json([
            'success' => false,
            'message' => 'You have already submitted an offer.'
        ], 400);
    }

    // Validate input
    $data = $request->validate([
        'fulfilled_qty' => 'required_if:offer_status,accepted|numeric|min:0.01',
        'offer_status'  => 'required|in:accepted,rejected',
        'description'   => 'nullable|string',
    ]);

    $data['pre_order_id'] = $preOrder->id;
    $data['farm_id'] = $farm->id;

    try {
        DB::beginTransaction();

        // Create the order detail record
        $orderDetail = OrderDetail::create($data);

        // Notify vendor for this offer
        $notification = $this->notificationService->notifyVendor($orderDetail);
        if (!$notification) {
            throw new \Exception("Failed to create notification for vendor.");
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'data' => $orderDetail
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 *  Vendor confirms or rejects an offer
 */
// public function confirmOffer(Request $request, $id)
// {
//     $user = Auth::user();

//     //  Only vendors can confirm or reject offers
//     if ($user->role !== 'vendor') {
//         return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
//     }

//     //  Load order detail with relationships
//     $orderDetail = OrderDetail::with('preOrder.product')->findOrFail($id);

//     //  Prevent vendor from confirming another vendor’s pre-order
//     if ($orderDetail->preOrder->user_id !== $user->id) {
//         return response()->json(['success' => false, 'message' => 'Not your pre-order'], 403);
//     }

//     //  Prevent multiple confirmation attempts
//     if (in_array($orderDetail->offer_status, ['confirmed', 'rejected'])) {
//         return response()->json(['success' => false, 'message' => 'Offer already handled'], 400);
//     }

//     //  Validate request input
//     $data = $request->validate([
//         'offer_status' => 'required|in:confirmed,rejected',
//     ]);

//     try {
//         DB::beginTransaction();

//         // Update offer status
//         $orderDetail->update($data);
//         $orderDetail->refresh()->load('preOrder.product');

//         if ($data['offer_status'] === 'confirmed') {

//             $product           = $orderDetail->preOrder->product;
//             $productId         = $product->id;
//             $farmId            = $orderDetail->farm_id;
//             $farmOfferQty      = floatval($orderDetail->fulfilled_qty);  // Farm’s total supply
//             $vendorRequestQty  = floatval($orderDetail->preOrder->qty);

//             //  Calculate quantity distribution
//             $vendorQty  = min($farmOfferQty, $vendorRequestQty);
//             $surplusQty = max(0, $farmOfferQty - $vendorRequestQty);

//             //  Calculate harvest date
//             $harvestedDate = $this->calculateHarvestDate($product->name);

//             //  Step 1: Create crop record (represents the full farm supply)
//             $crop = Crop::create([
//                 'farm_id'       => $farmId,
//                 'product_id'    => $productId,
//                 'name'          => $product->name . ' Crop',
//                 'qty'           => $farmOfferQty,
//                 'image'         => $product->image ?? null,
//                 'status'        => 0, // 0 = planting
//                 'harvest_date'  => $harvestedDate,
//             ]);

//             //  Step 2: Link crop to the order detail
//             $orderDetail->update([
//                 'crop_id'       => $crop->id,
//                 'fulfilled_qty' => $vendorQty,
//             ]);

//             //  Step 3: Create MarketSupply if surplus exists
//             if ($surplusQty > 0) {
//                 MarketSupply::create([
//                     'farm_id'        => $farmId,
//                     'crop_id'        => $crop->id,
//                     'product_id'     => $productId,
//                     'available_qty'  => $surplusQty,
//                     'unit'           => $orderDetail->unit ?? 'kg',
//                     'availability'   => $harvestedDate,
//                 ]);
//             }

//             //  Step 4: Update pre-order status & notify farm
//             $preOrderStatus = $this->preOrderService->updatePreOrderStatus($orderDetail->pre_order_id);
//             if (!$preOrderStatus) {
//                 throw new \Exception("Failed to update status in pre-order");
//             }

//             $notification = $this->notificationService->notifyFarm($orderDetail);
//             if (!$notification) {
//                 throw new \Exception("Failed to create notification for farmer.");
//             }
//         }

//         DB::commit();

//         return response()->json([
//             'success' => true,
//             'message' => "Offer {$data['offer_status']} successfully",
//             'data'    => $orderDetail,
//         ]);

//     } catch (\Exception $e) {
//         DB::rollBack();
//         return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
//     }
// }
    public function confirmOffer(Request $request, $id)
{
    $user = Auth::user();

    if ($user->role !== 'vendor') {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $orderDetail = OrderDetail::with('preOrder.product')->findOrFail($id);

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

        $orderDetail->update($data);
        $orderDetail->refresh()->load('preOrder.product');

        $preOrder = $orderDetail->preOrder;
        $vendorRequestedQty = floatval($preOrder->qty);

        $alreadyConfirmedQty = OrderDetail::where('pre_order_id', $preOrder->id)
            ->where('offer_status', 'confirmed')
            ->where('id', '!=', $orderDetail->id)
            ->sum('fulfilled_qty');

        $remainingQty = $vendorRequestedQty - $alreadyConfirmedQty;

        // -----------------------------
        // 1. Handle CONFIRMATION
        // -----------------------------
        if ($data['offer_status'] === 'confirmed') {

            $farmOfferQty = floatval($orderDetail->fulfilled_qty);   // what farm offered
            $allocatedQty = min($farmOfferQty, $remainingQty);        // vendor needs
            $surplusQty   = max(0, $farmOfferQty - $allocatedQty);   // leftover

            $product = $orderDetail->preOrder->product;
            $harvestedDate = $this->calculateHarvestDate($product->name);

            // Create crop (Option A → full farmOfferQty)
            $crop = Crop::create([
                'farm_id'      => $orderDetail->farm_id,
                'product_id'   => $product->id,
                'name'         => $product->name . ' Crop',
                'qty'          => $farmOfferQty,  // FULL OFFER from farm (Option A)
                'image'        => $product->image ?? null,
                'status'       => 0,
                'harvest_date' => $harvestedDate,
            ]);

            // Update order detail → only allocated amount
            $orderDetail->update([
                'crop_id'       => $crop->id,
                'fulfilled_qty' => $allocatedQty,
            ]);

            // Surplus exists  send to market supply
                if ($surplusQty > 0) {
                MarketSupply::create([
                    'farm_id'       => $orderDetail->farm_id,
                    'crop_id'       => $crop->id,
                    'product_id'    => $product->id,
                    'available_qty' => $surplusQty,
                    'unit'          => $orderDetail->unit ?? 'kg',
                    'availability'  => $crop->harvest_date,
                ]);

                $this->notificationService->notifyFarm(
                    $orderDetail,
                    "Vendor confirmed {$allocatedQty} {$orderDetail->unit} from your offer. 
                     The remaining {$surplusQty} {$orderDetail->unit} has been added to Market Surplus."
                );
            } else {
                $this->notificationService->notifyFarm(
                    $orderDetail,
                    "Vendor confirmed your supply of {$allocatedQty} {$orderDetail->unit}."
                );
            }

            $alreadyConfirmedQty += $allocatedQty;

            // Auto reject others if fully fulfilled
            if ($alreadyConfirmedQty >= $vendorRequestedQty) {
                $pendingOffers = OrderDetail::where('pre_order_id', $preOrder->id)
                    ->where('offer_status', 'accepted')
                    ->where('id', '!=', $orderDetail->id)
                    ->get();

                foreach ($pendingOffers as $offer) {
                    $offer->update(['offer_status' => 'rejected']);
                    $this->notificationService->notifyFarm(
                        $offer,
                        "Your offer was not selected because the vendor has fulfilled the requirement."
                    );
                }
            }
        }

        // -----------------------------
        // 2. Handle REJECTION
        // -----------------------------
        if ($data['offer_status'] === 'rejected') {
            $this->notificationService->notifyFarm(
                $orderDetail,
                "Your offer was not selected for this pre-order."
            );
        }

        $this->preOrderService->updatePreOrderStatus($preOrder->id);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => "Offer {$data['offer_status']} successfully",
            'data'    => $orderDetail,
            'remaining_qty' => max(0, $vendorRequestedQty - $alreadyConfirmedQty),
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
        str_contains($productName, 'tomato') => now()->addDays(65),
        str_contains($productName, 'cherry') => now()->addDays(55),
        str_contains($productName, 'cucumber') => now()->addDays(45),
        str_contains($productName, 'eggplant') => now()->addDays(75),
        str_contains($productName, 'corn') => now()->addDays(80),
        str_contains($productName, 'carrot') => now()->addDays(70),
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

        // Allowed statuses per role
        $allowedStatuses = [
            'farmer' => ['accepted', 'rejected', 'confirmed'],
            'vendor' => ['accepted', 'confirmed', 'rejected'],
        ];

        $role = $user->role;

        if (!isset($allowedStatuses[$role])) {
            return response()->json([
                'success' => false,
                'message' => 'Role not supported',
            ], 403);
        }

        $inputStatus = $request->input('offer_status');

        if ($inputStatus && !in_array($inputStatus, $allowedStatuses[$role])) {
            return response()->json([
                'success' => false,
                'message' => "Invalid status for your role"
            ], 400);
        }

        // Load relations properly
        $query = OrderDetail::with([
            'preOrder' => function ($q) {
                $q->with(['product', 'user']); // eager load nested user & product
            },
            'farm.owner'
        ]);

        // Filter based on role
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
                $q->where('user_id', $user->id); // only vendor's pre-orders
            });
        }

        if ($inputStatus) {
            $query->where('offer_status', $inputStatus);
        }

        // Paginate
        $orderDetails = $query->orderBy('created_at', 'desc')->paginate(5);

        // Map response safely
        $data = $orderDetails->getCollection()->map(function ($orderDetail) use ($role) {
            $preOrder = $orderDetail->preOrder;
            $farm = $orderDetail->farm;
            $farmerUser = optional($farm->owner);
            $vendorUser = optional($preOrder->user);
            $vendorName = $vendorUser
            ? trim($vendorUser->first_name . ' ' . $vendorUser->last_name)
            : 'Unknown Vendor';

            return [
                'order_detail_id' => $orderDetail->id,
                'pre_order_id'    => $preOrder->id,
                'vendorName'      => $role === 'farmer'
                                    ? $vendorName 
                                    : $farm->name ?? 'Unknown Farm',
                'user_id' => $role === 'farmer'
                                    ? $vendorUser->id ?? 0   
                                    : $farmerUser->id ?? 0,
                'product_name'    => optional($preOrder->product)->name ?? 'Unknown Product',
                'product_image'   => optional($preOrder->product)->image,
                'requested_qty'   => $preOrder->qty,
                'fulfilled_qty'   => $orderDetail->fulfilled_qty,
                'location'        => $preOrder->location,
                'note'            => $preOrder->note ?? 'No notes',
                'delivery_date'   => optional($preOrder->delivery_date)?->format('Y-m-d') ?? '',
                'offer_status'    => $orderDetail->offer_status,
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
    public function getPreOrderOffers($preOrderId)
{
    $user = auth()->user();

    if (!$user || $user->role !== 'vendor') {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized',
        ], 403);
    }

    $preOrder = PreOrder::with(['orderDetails.farm.owner', 'product', 'user'])
        ->where('user_id', $user->id) // ensure vendor owns the pre-order
        ->find($preOrderId);

    if (!$preOrder) {
        return response()->json([
            'success' => false,
            'message' => 'Pre-order not found',
        ], 404);
    }

    $offers = $preOrder->orderDetails->map(function($detail) {
        $farm = $detail->farm;
        $farmerUser = optional($farm->owner);
        return [
            'order_detail_id' => $detail->id,
            'farm_name'       => $farm->name ?? 'Unknown Farm',
            'farmer_user_id'  => $farmerUser->id ?? 0,
            'fulfilled_qty'   => $detail->fulfilled_qty,
            'status'          => $detail->offer_status,
            'note'            => $detail->description ?? '',
        ];
    });

    return response()->json([
        'success' => true,
        'pre_order_id' => $preOrder->id,
        'product_name' => $preOrder->product->name ?? 'Unknown Product',
        'requested_qty'=> $preOrder->qty,
        'offers'       => $offers,
    ]);
}



}
