<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MarketSupply;
use Illuminate\Http\Request;

class MarketSupplyController extends Controller
{
    //
    public function index(){

        $supplies = MarketSupply::with([
                'farm:id,name',
                'product:id,name,image'
            ])
            ->where('available_qty', '>', 0)
            ->whereDate('availability', '>=', now())  
                ->get();
    
    return response()->json([
        'success' => true,
        'data'    => $supplies
    ]);
    }
        // Get single market supply info for "Order Remaining"
    public function show($id)
    {
        $supply = MarketSupply::with(['farm:id,name', 'product:id,name,image'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'market_supply_id' => $supply->id,
                'product_id'       => $supply->product_id,
                'product_name'     => $supply->product->name,
                'farm_id'          => $supply->farm->id,
                'farm_name'        => $supply->farm->name,
                'available_qty'    => $supply->available_qty, // default quantity
                'unit'             => $supply->unit,
            ]
        ]);
    }

    public function redirectToPreOrder($id){
        $supply = MarketSupply::with(['farm:id,name','product']);
    }
}
