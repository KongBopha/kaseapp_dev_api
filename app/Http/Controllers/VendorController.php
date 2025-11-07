<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Vendor;
use App\Models\User;
use App\Services\FileUploadService;

class VendorController extends Controller
{
    //
    public function updateVendorProfile(Request $request,FileUploadService $uploadService ){
        $user = auth()->user();

        if($user->role!=='vendor'){
            return response()->json([
                'success'=>false,
                'message'=>'Only vendors can update vendor profile'
            ], 403);
        }

        $vendor = Vendor::where('owner_id',$user->id)->firstOrFail();


        $validator = Validator::make($request->all(),[
            'name' => 'sometimes|string|max:255',
            'vendor_type' => 'sometimes|in:retailer,wholesaler',
            'address' => 'sometimes|string|max:255',
            'about' => 'sometimes|string|max:1000',
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
 

        if ($request->hasFile('logo')) {
            $validated['logo'] = $uploadService->uploadFile(
                $request->file('logo'),
                'vendor_logos'
        );
        }

        $vendor->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Vendor profile updated successfully',
            'vendor' => $vendor->fresh()
        ], 200);
    
    }
}
