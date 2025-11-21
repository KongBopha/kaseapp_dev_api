<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RoleRequest;
use App\Models\Farm;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;


class AuthController extends Controller
{
    
    public function register(Request $request)
{
    $validator = Validator::make($request->all(), [
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'sex' => 'nullable|in:male,female,other',
        'email' => 'nullable|string|email|max:255|unique:users,email',
        'phone' => 'nullable|string|max:50|unique:users,phone',
        'password' => [
            'required',
            'string',
            'confirmed',
            Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
        ],
    ]);

    //  If validation fails, return JSON  
    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422);
    }

    $validated = $validator->validated();

    //  check email or phone 
    if (empty($validated['email']) && empty($validated['phone'])) {
        return response()->json([
            'success' => false,
            'message' => 'Email or Phone is required'
        ], 400);
    }

    try {
        // Create user
        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'sex' => $validated['sex'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'role' => 'consumer',
            'password' => Hash::make($validated['password']),
        ]);

        // Generate token
        $token = $user->createToken(
            'auth_token',
            ['*'],
            now()->addMinutes(60)
        )->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registered successfully',
            'access_token' => $token,
            'user' => $user
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'User creation failed: ' . $e->getMessage()
        ], 500);
    }
    }

    // login
public function login(Request $request)
{
    // Rate limiting
    $executed = RateLimiter::attempt(
        'login-attempt:' . $request->ip(),
        $perMinute = 5,
        function () {}
    );

    if (!$executed) {
        return response()->json([
            'success' => false,
            'message' => 'Too many login attempts. Please try again later.',
        ], 429);
    }

    $validator = Validator::make($request->all(), [
        'login' => 'required|string|max:255',
        'password' => 'required|string',
 
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422);
    }

    $validated = $validator->validated();

    // Find user by email OR phone
    $user = User::where('phone', $validated['login'])
            ->orWhere('email', $validated['login'])
            ->first();

    if (!$user || !Hash::check($validated['password'],
     $user->password)) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials',
        ], 401);
    }
    \Log::info("Login input: " . $validated['login']);
    \Log::info("Password input: " . $validated['password']);


    // Create token
    $token = $user->createToken(
        'auth_token',
        ['*'],
        now()->addMinutes(60)
    )->plainTextToken;

    return response()->json([   
        'success' => true,
        'message' => 'Login successful',
        'access_token' => $token,
        'user' => $user,
    ], 200);
    }


    // get current user 
    public function me(Request $request)
    {
        $user = Auth::user();

        If(!$user){
            return response()->json([ 
            'success' => true,
            'name' => 'Guest User',
            'role' => 'consumer'
            ], 200);
        }
        return response()->json([
            'success' => true,
            'user' => $user
        ]);
    }

    // user profile photo
    
    public function updateProfile(Request $request, FileUploadService $uploadService){
        $user = auth()->user();

        $validator = Validator::make($request->all(),[
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'sex' => 'sometimes|in:male,female,other',
            'address' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,'.$user->id,
            'phone' => 'sometimes|string|max:50|unique:users,phone,'.$user->id,
            'profile_url' => 'sometimes|image|mimes:jpg,png,jpeg|max:2048',
        ]);

        if ($validator->fails()) {   
            return response()->json([
                'success' => false,
                'message' => 'Validation Failed',
                'errors'  => $validator->errors()
            ], 422);
        }
        $validated = $validator->validated();

        if ($request->hasFile('profile_url')) {
            $validated['profile_url'] = $uploadService->uploadFile(
                $request->file('profile_url'),
                'profile_photos'
            );
        }


        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => $user
        ], 200);

    }

    // logout user
    public function logout(Request $request)
    {
        $user = $request->user();

        If($user){
            $currentToken ->user()->currentAccessToken();
            if($currentToken){
                $currentToken->delete();
            }
        
            return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
            'user' => null  
        ], 200);
        }
        
        return response()->json([
        'success' => true,
        'message' => 'No active session',
        'user' => null
    ], 200);
    }
    public function upgradeToFarmer(Request $request)
    {
        $user = auth()->user();

        if (in_array($user->role, ['farmer', 'vendor'])) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a role.'
            ], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'about' => 'nullable|string|max:1000',
            'cover' => 'nullable|string',
            'logo' => 'nullable|string',
        ]);

        $roleRequest = RoleRequest::create([
            'user_id' => $user->id,
            'requested_role' => 'farmer',
            'details' => $validated,  
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Farmer upgrade request submitted. Please await approval.',
            'request' => $roleRequest
        ]);
    }

    public function upgradeToVendor(Request $request)
    {
        $user = auth()->user();

        if (in_array($user->role, ['farmer', 'vendor'])) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a role.'
            ], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'vendor_type' => 'required|in:retailer,wholesaler',
            'address' => 'nullable|string|max:255',
            'about' => 'nullable|string|max:1000',
            'logo' => 'nullable|mimes:png,jpg,jpeg|max:2048',
        ]);

        if ($request->hasFile('profile_url')) {
            $validated['profile_url'] = $uploadService->uploadFile(
                $request->file('profile_url'),
                'profile_photos'
            );
        }

        $roleRequest = RoleRequest::create([
            'user_id' => $user->id,
            'requested_role' => 'vendor',
            'details' => $validated, // store validated info
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vendor upgrade request submitted. Please await approval.',
            'request' => $roleRequest
        ]);
    }

    // view user profile

    public function showProfile($id)
    {
        $user = User::with(['farms', 'vendors'])->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }           

        $profile = [
            'id'          => $user->id,
            'first_name'  => $user->first_name,
            'last_name'   => $user->last_name,
            'name'        => $user->first_name . ' ' . $user->last_name,
            'email'       => $user->email,
            'phone'       => $user->phone,
            'role'        => $user->role,
            'profile_url' => $user->profile_url,
        ];

        // Include farm info if user has farms
        if ($user->farms->isNotEmpty()) {
            $farm = $user->farms->first();
            $profile['farm'] = [
                'id'      => $farm->id,
                'owner_id' => $farm->owner_id,
                'name'    => $farm->name,
                'address' => $farm->address,
                'about'   => $farm->about,
                'status'  => $farm->status,
                'logo'    => $farm->logo,
                'cover'   => $farm->cover,
            ];
        }

        // Include vendor info if user has vendors
        if ($user->vendors->isNotEmpty()) {
            $vendor = $user->vendors->first();
            $profile['vendor'] = [
                'id'          => $vendor->id,
                'name'        => $vendor->name,
                'vendor_type' => $vendor->vendor_type,
                'address'     => $vendor->address,
                'about'       => $vendor->about,
                'logo'        => $vendor->logo,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $profile
        ]);
    }
    public function approveRoleRequest($id)
    {
        $admin = auth()->user();
        if ($admin->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $user = User::findOrFail($id);

        if (!$user->requested_role) {
            return response()->json(['success' => false, 'message' => 'No pending role request'], 400);
        }

        try {
            DB::beginTransaction();

            $roleRequest = RoleRequest::where('user_id', $user->id)
                ->where('requested_role', $user->requested_role)
                ->latest()
                ->first();

            if (!$roleRequest) {
                throw new \Exception('Role request record not found.');
            }

            // Update user role
            $user->update([
                'role' => $user->requested_role,
                'requested_role' => null,
                'is_approved' => 1,
            ]);

            // Update role request status
            $roleRequest->update([
                'status' => 'accepted',
                'approved_by' => $admin->id,
            ]);

            DB::commit();

            $this->notifyRoleRequest($user->id, $user->role, 'accepted');

            return response()->json([
                'success' => true,
                'message' => 'Role request approved successfully',
                'user' => $user
            ], 200);

        } catch (QueryException | \Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve role request: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function rejectRoleRequest($id)
    {
        $admin = auth()->user();
        if ($admin->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $user = User::findOrFail($id);

        if (!$user->requested_role) {
            return response()->json(['success' => false, 'message' => 'No pending role request'], 400);
        }

        try {
            DB::beginTransaction();

            $roleRequest = RoleRequest::where('user_id', $user->id)
                ->where('requested_role', $user->requested_role)
                ->latest()
                ->first();

            if ($roleRequest) {
                $roleRequest->update([
                    'status' => 'rejected',
                    'approved_by' => $admin->id,
                ]);
            }

            $user->update([
                'requested_role' => null,
                'is_approved' => 0,
            ]);

            DB::commit();

            $this->notifyRoleRequest($user->id, $user->role, 'rejected');

            return response()->json([
                'success' => true,
                'message' => 'Role request rejected successfully',
                'user' => $user
            ], 200);

        } catch (QueryException | \Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject role request: ' . $e->getMessage(),
            ], 500);
        }
    }

}