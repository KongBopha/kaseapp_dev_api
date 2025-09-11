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
                ->get();
    
    return response()->json([
        'success' => true,
        'data'    => $supplies
    ]);
    }
}
