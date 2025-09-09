<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FarmController extends Controller
{
    public function updateFarmProfile(Request $request, FileUploadService $uploadService)
    {
        $user = auth()->user();

        if ($user->role !== 'farmer') {
            return response()->json([
                'success' => false,
                'message' => 'Only farmers can update farm profile'
            ], 403);
        }

        $farm = Farm::where('owner_id', $user->id)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:1000',
            'cover' => 'sometimes|image|mimes:jpg,png,jpeg|max:2048',
            'logo' => 'sometimes|image|mimes:jpg,png,jpeg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        if ($request->hasFile('cover')) {
            $validated['cover'] = $uploadService->uploadFile($request->file('cover'), 'farm_covers');
        }

        if ($request->hasFile('logo')) {
            $validated['logo'] = $uploadService->uploadFile($request->file('logo'), 'farm_logos');
        }

        $farm->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Farm profile updated successfully',
            'farm' => $farm
        ], 200);
    }
}
