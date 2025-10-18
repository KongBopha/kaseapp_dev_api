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
        'phone' => 'required|string|max:255',
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
    $user = User::where('phone', $validated['phone'])->first();

    if (!$user || !Hash::check($validated['password'], $user->password)) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials',
        ], 401);
    }

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
            'profile_photo' => 'sometimes|image|mimes:jpg,png,jpeg|max:2048',
        ]);

        if ($validator->fails()) {   
            return response()->json([
                'success' => false,
                'message' => 'Validation Failed',
                'errors'  => $validator->errors()
            ], 422);
        }
        $validated = $validator->validated();

        if ($request->hasFile('profile_photo')) {
            $validated['profile_url'] = $uploadService->uploadFile($request->file('profile_photo'), 'profile_photos');
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
            'user' => null // explicitly return null user
        ], 200);
        }
        
        return response()->json([
        'success' => true,
        'message' => 'No active session',
        'user' => null
    ], 200);
    }
    // reset password
    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols()
            ],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password does not match'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ], 200);
    }

 public function upgradeToFarmer(Request $request)
{
    $user = auth()->user();

    if (in_array($user->role, ['farmer', 'vendor'])) {
        return response()->json([
            'success' => false,
            'message' => 'You already have a business role.'
        ], 400);
    }

    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'address' => 'nullable|string|max:255',
        'about' => 'nullable|string|max:1000',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors'  => $validator->errors(),
        ], 422);
    }

    // Create role request
    $roleRequest = \App\Models\RoleRequest::create([
        'user_id' => $user->id,
        'requested_role' => 'farmer',
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Farmer upgrade request submitted. Awaiting admin approval.',
        'request' => $roleRequest
    ]);
}

public function upgradeToVendor(Request $request)
{
    $user = auth()->user();

    if (in_array($user->role, ['farmer', 'vendor'])) {
        return response()->json([
            'success' => false,
            'message' => 'You already have a vendor role.'
        ], 400);
    }

    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'vendor_type' => 'required|in:retailer,wholesaler',
        'address' => 'nullable|string|max:255',
        'about' => 'nullable|string|max:1000',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors'  => $validator->errors(),
        ], 422);
    }

    // Create role request
    $roleRequest = \App\Models\RoleRequest::create([
        'user_id' => $user->id,
        'requested_role' => 'vendor',
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Vendor upgrade request submitted. Awaiting admin approval.',
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

        $user->update([
            'role' => $user->requested_role,
            'requested_role' => null,
            'is_approved' => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role request approved successfully',
            'user' => $user
        ], 200);
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

        $user->update([
            'requested_role' => null,
            'is_approved' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role request rejected successfully',
            'user' => $user
        ], 200);
    }

}