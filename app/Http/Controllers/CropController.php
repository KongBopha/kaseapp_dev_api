<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Crop;
use App\Models\Farm;
use App\Models\Product;
use App\Models\OrderDetail;
use App\Models\MarketSupply;
use Illuminate\Support\Facades\Validator;

class CropController extends Controller
{
    public function createCrop(Request $request)
    {
        $user = Auth::user();
        $farm = Farm::where('owner_id', $user->id)->first();

        if ($user->role !== 'farmer') {
            return response()->json(['success' => false, 'message' => 'Only farmers can create crops.'], 403);
        }

        if (!$farm || $request->farm_id != $farm->id) {
            return response()->json(['success' => false, 'message' => 'You can only create crops for your own farm.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'farm_id' => 'required|integer|exists:farms,id',
            'product_id' => 'required|integer|exists:products,id',
            'name' => 'required|string|max:255',
            'qty' => 'required|integer|min:1',
            'image' => 'nullable|string|max:255',
            'harvest_date' => 'required|date|after_or_equal:today',
            'status' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $existingCrop = Crop::where('farm_id', $request->farm_id)
            ->where('product_id', $request->product_id)
            ->first();

        $crop = Crop::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Crop created successfully',
            'data' => $crop,
            'remaining_qty' => $remaining_qty
        ], 201);
    }
}
