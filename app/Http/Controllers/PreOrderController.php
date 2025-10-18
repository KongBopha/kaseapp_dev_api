<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PreOrder;
use App\Models\Crop;
use App\Models\Product;
use App\Models\User;
use App\Models\Farm;
use App\Models\OrderDetail;
use App\Models\MarketSupply;
use Carbon\Carbon;
use App\Services\NotificationService;
use App\Services\PreOrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;



class PreOrderController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService,PreOrderService $preOrderService)
    {
        $this->notificationService = $notificationService;
        $this->preOrderService = $preOrderService;
    }

    // Vendor creates a pre-order
    public function store(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'vendor') {
            return response()->json(['success' => false, 'message' => 'Only vendors can create pre-orders.'], 401);
        }

        $data = $request->validate([
            'product_id'    => 'required|exists:products,id',
            'qty'           => 'required|integer|min:1',
            'delivery_date' => 'nullable|date',
            'location'      => 'nullable|string',
            'note_text'     => 'nullable|string',
        ]);

        //validate the date

        if(!empty($data['delivery_date'])){
            $deliveryDate = Carbon::parse($data['delivery_date'])->startOfDay();

        $today = Carbon::today();
        if ($deliveryDate->lt($today)) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery date must be today or a future date.',
            ], 422);
        }
        }

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
 
    /**
     * List order details for the authenticated farmer
     */
    public function listPreOrders(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please log in again.',
            ], 401);
        }
        if (!in_array($user->role, ['farmer', 'vendor'])) {
        return response()->json([
            'success' => true,
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 0,
                'total' => 0,
            ],
            'message' => 'No pre-orders available for this role',
        ], 200);
        }

        // Build query depending on role
        if ($user->role === 'farmer') {
            $query = $this->buildFarmerQuery($user);
        } elseif ($user->role === 'vendor') {
            $query = $this->buildVendorQuery($user, $request);
        }

        $preOrders = $query->orderBy('created_at', 'desc')->paginate(5);

        return response()->json([
            'success' => true,
            'meta' => [
                'current_page' => $preOrders->currentPage(),
                'last_page'    => $preOrders->lastPage(),
                'per_page'     => $preOrders->perPage(),
                'total'        => $preOrders->total(),
            ],
            'data' => $this->transformPreOrders($preOrders),
        ]);
    }

    private function buildFarmerQuery($user)
    {
        return PreOrder::with(['user', 'product'])
            ->where('status', 'pending')
            ->whereDoesntHave('orderDetails', function ($q) use ($user) {
                $q->where('farm_id', $user->farm_id);
            });
    }

    private function buildVendorQuery($user, $request)
    {
        $query = PreOrder::with(['user', 'product'])
            ->where('user_id', $user->id);

        if ($request->has('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        return $query;
    }

    private function transformPreOrders($preOrders)
    {
        return $preOrders->getCollection()->map(function ($preOrder) {
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
    }

    /**
     * Update a pre-order (Vendor only, status = pending)
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'vendor') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $preOrder = PreOrder::where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$preOrder) {
            return response()->json(['success' => false, 'message' => 'Pre-order not found or cannot be edited'], 404);
        }

        if ($preOrder->orderDetails()->exists()) {
            return response()->json(['success' => false, 'message' => 'Cannot edit pre-order after farm responses'], 403);
        }

        $validated = $request->validate([
            'product_id'    => 'sometimes|exists:products,id',
            'qty'           => 'sometimes|integer|min:1',
            'delivery_date' => 'nullable|date|after_or_equal:today',
            'location'      => 'nullable|string|max:255',
            'note_text'     => 'nullable|string|max:500',
        ]);

        $preOrder->update($validated);

        return response()->json(['success' => true, 'message' => 'Pre-order updated', 'data' => $preOrder]);
    }

    /**
     * Delete/Cancel a pre-order (Vendor only, status = pending)
     */
    public function destroy($id)
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'vendor') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $preOrder = PreOrder::where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$preOrder) {
            return response()->json(['success' => false, 'message' => 'Pre-order not found or cannot be canceled'], 404);
        }

        if ($preOrder->orderDetails()->exists()) {
            return response()->json(['success' => false, 'message' => 'Cannot cancel pre-order after farm responses'], 403);
        }

        // Soft delete by updating status
        $preOrder->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'message' => 'Pre-order canceled']);
    }

    /**
     * Read pre-order (Vendor only, status = pending)
     */
    public function show($id)
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'vendor') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $preOrder = PreOrder::with('product')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$preOrder) {
            return response()->json(['success' => false, 'message' => 'Pre-order not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $preOrder]);
    }

    public function storeFromSurplus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|exists:users,id', 
            'farm_id' => 'required|exists:farms,id',
            'market_supply_id' => 'required|exists:market_supplies,id',
            'quantity' => 'required|numeric|min:0',
            'unit' => 'required|string',
            'delivery_date' => 'required|date',
            'location' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        DB::beginTransaction();

        try {
            $userId = $data['user_id'] ?? Auth::id();  

            // Get farm crop linked to the market supply
            $supply = MarketSupply::findOrFail($data['market_supply_id']);
            $crop = Crop::findOrFail($supply->crop_id);

            if ($data['quantity'] > $supply->available_qty) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quantity exceeds available market supply',
                ], 400);
            }

            //  Create new PreOrder for the surplus
            $preOrder = PreOrder::create([
                'user_id'       => $userId,  
                'product_id'    => $crop->product_id,
                'crop_id'       => $crop->id,
                'qty'           => $data['quantity'],
                'location'      => $data['location'] ?? null,
                'note'          => $data['note'] ?? 'Surplus order',
                'delivery_date' => $data['delivery_date'],
                'status'        => 'pending',
            ]);

            $orderDetail = OrderDetail::create([
                'pre_order_id'   => $preOrder->id,
                'farm_id'        => $data['farm_id'],
                'product_id'     => $crop->product_id,
                'crop_id'        => $crop->id,
                'fulfilled_qty'  => $data['quantity'],
                'unit'           => $data['unit'],
                'offer_status'   => 'confirmed',
                'description'    => 'Surplus offer',
            ]);

            $supply->available_qty -= $data['quantity'];
            $supply->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Surplus order successfully created',
                'data' => [
                    'pre_order' => $preOrder,
                    'order_detail' => $orderDetail,
                    'remaining_qty' => $supply->available_qty,
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'vendor') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $query = PreOrder::with('product')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc');

        // Filter by from_date & to_date
        if ($request->has('from_date') && $request->has('to_date')) {
            $query->whereBetween('created_at', [
                $request->input('from_date'),
                $request->input('to_date')
            ]);
        }

        // Filter by time filters: today, this_week, next_week
        if ($request->filled('time_filter')) {
            $timeFilter = $request->input('time_filter');
            $today = now()->startOfDay();

            switch ($timeFilter) {
                case 'today':
                    $query->whereDate('created_at', $today);
                    break;

                case 'this_week':
                    $query->whereBetween('created_at', [
                        $today->startOfWeek(),
                        $today->endOfWeek()
                    ]);
                    break;

                case 'next_week':
                    $nextWeekStart = $today->copy()->addWeek()->startOfWeek();
                    $nextWeekEnd   = $today->copy()->addWeek()->endOfWeek();
                    $query->whereBetween('created_at', [
                        $nextWeekStart,
                        $nextWeekEnd
                    ]);
                    break;

                default:
                    break;
            }
        }

        // Search by product name
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Paginate 5 results per page
        $preOrders = $query->paginate(5);

        // Map the data
        $data = $preOrders->getCollection()->map(function ($preOrder) {
            return [
                'id'            => $preOrder->id,
                'product_name'  => $preOrder->product->name,
                'product_image' => $preOrder->product->image,
                'quantity'      => $preOrder->qty . ' ' . $preOrder->product->unit,
                'delivery_date' => $preOrder->delivery_date ? $preOrder->delivery_date->format('Y-m-d') : null,
                'status'        => ucfirst($preOrder->status),
                'note'          => $preOrder->note ?? 'No notes',
            ];
        });

        $paginated = $preOrders->toArray();
        $paginated['data'] = $data;

        return response()->json([
            'success' => true,
            'data'    => $paginated
        ]);
    }

    public function trendingProducts() {
    $trending = PreOrder::select('product_id')
        ->selectRaw('COUNT(*) as pre_order_count')
        ->groupBy('product_id')
        ->orderByDesc('pre_order_count')
        ->with('product') // eager load product details
        ->take(3) // top 5 trending products
        ->get();

    return response()->json([
        'success' => true,
        'data' => $trending
    ]);
}
}