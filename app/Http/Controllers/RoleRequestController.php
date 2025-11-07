<?php

namespace App\Http\Controllers;

use App\Models\RoleRequest;
use App\Models\User;
use App\Models\Farm;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\NotificationService;
use App\Constants\NotificationTypeEnum;
use Illuminate\Support\Facades\DB;


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
 public function update(Request $request, $id, NotificationService $notificationService)
{
    $request->validate([
        'status' => 'required|in:approved,rejected'
    ]);

    $admin = Auth::user();
    if ($admin->role !== 'admin') {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    try {
        DB::beginTransaction();

        $roleRequest = RoleRequest::with('user')->findOrFail($id);

        // Call service to handle role request update, user role, and notification
        $updatedRequest = $notificationService->notifyRoleRequest(
            $roleRequest,
            $request->status
        );

        if (!$updatedRequest) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process role request. Notification or update failed.'
            ], 500);
        }

        // Reload user to get updated role
        $updatedRequest->load('user');

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => "Role request {$request->status} successfully.",
            'request' => $updatedRequest
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error("Role request update failed: {$e->getMessage()}");

        return response()->json([
            'success' => false,
            'message' => 'Something went wrong while processing the role request.'
        ], 500);
    }
}




}
