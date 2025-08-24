<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $products = Product::all();
        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $validated = $request->validate([
            'owner_id' => 'required|integer|exists:users,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'required|string|max:50',
            'image' => 'nullable|string|max:255'
        ]);
        
        // create product
        $product = Product::create($validated);

        return response()->json([
            'success' => true,
            'message' => "Product created successfully",
            'data'=> $product
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'owner_id' => 'required|integer|exists:users,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'required|string|max:50',
            'image' => 'nullable|string|max:255'
        ]);

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Product updated successfully",
            'data'=> $product
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)

    {
        $product = Product::findOrFail($id);
        $product->delete();
        return response()->json([
            'success' => true,
            'message' => "Product deleted successfully"
        ]);

    }
}
