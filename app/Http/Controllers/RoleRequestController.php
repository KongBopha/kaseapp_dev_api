<?php

namespace App\Http\Controllers;

use App\Models\RoleRequest;
use App\Models\User;
use App\Models\Farm;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleRequestController extends Controller
{
    // Admin views all pending requests
    public function index()
    {
        $requests = RoleRequest::with('user')
            ->where('status', 'pending')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    // Admin approves or rejects a request
public function update(Request $request, $id)
{
    $request->validate([
        'status' => 'required|in:approved,rejected'
    ]);

    $admin = Auth::user();
    if ($admin->role !== 'admin') {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $roleRequest = RoleRequest::with('user')->findOrFail($id);
    $user = $roleRequest->user;

    if ($request->status === 'approved') {
        // Update user's role
        $user->update([
            'role' => $roleRequest->requested_role
        ]);

         if ($roleRequest->requested_role === 'farmer') {
            // Check if farm exists
            if (!$user->farm) {
                \App\Models\Farm::create([
                    'user_id' => $user->id,
                    'name' => $user->first_name . "'s Farm",
                    'address' => $user->address ?? null,
                    'about' => null,  
                ]);
            }
        } elseif ($roleRequest->requested_role === 'vendor') {
            if (!$user->vendor) {
                \App\Models\Vendor::create([
                    'user_id' => $user->id,
                    'company_name' => $user->first_name . "'s Vendor",
                    'vendor_type' => 'retailer',  
                    'address' => $user->address ?? null,
                    'description' => null,
                ]);
            }
        }

        $roleRequest->status = 'approved';
        $roleRequest->approved_by = $admin->id;

    } else {
        // Rejected request
        $roleRequest->status = 'rejected';
        $roleRequest->approved_by = $admin->id;
    }

    $roleRequest->save();

    return response()->json([
        'success' => true,
        'message' => "Role request {$request->status} successfully.",
        'request' => $roleRequest
    ]);
}

}
