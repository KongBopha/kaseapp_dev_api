<?php

namespace App\Http\Controllers;

use App\Models\User;
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

    // refresh token 

    public function refreshToken(Request $request)
    {
        $token = $request->bearerToken(); // Get token from header

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'No token provided',
                'access_token' => null
            ], 401);
        }

        // Find token in database
        $accessToken = $request->user()?->currentAccessToken();

        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token',
                'access_token' => null
            ], 401);
        }

        // Delete old token
        $accessToken->delete();

        // Create new token
        $newToken = $request->user()->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'access_token' => $newToken
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

 
    // upgrade to farmer
    public function upgradeToFarmer(Request $request)
    {
        $user = auth()->user(); // logged in user

        if ($user->role === 'farmer') {
            return response()->json([
                'success' => false,
                'message' => 'You are already a farmer'
            ], 201);
        } elseif ($user->role !== 'consumer') {
            return response()->json([
                'success' => false,
                'message' => 'Only consumers can upgrade to farmers'
            ], 403);
        } elseif ($user->role === 'vendor') {
            return response()->json([
                'success' => false,
                'message' => 'You are a vendor, you cannot upgrade to farmer'
            ], 403);
        }

        // validate farmer registration data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'about' => 'nullable|string|max:1000',
            'cover' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', 
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        //handle file upload
        if ($request->hasFile('cover')) {
            $data['cover'] = $request->file('cover')->store('covers', 'public');
        }
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        // create farmer profile
        $farmer = Farm::create([
            'owner_id' => $user->id,
            'name' => $validated['name'],
            'address' => $validated['address'],
            'description' => $validated['about'] ?? null,
            'cover' => $data['cover'] ?? null,
            'logo' => $data['logo'] ?? null,
        ]);

        $user->update([
            'role' => 'farmer',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Upgraded to farmer successfully',
            'farmer'    => $farmer,
            'user' => $user,
            ], 201);
    }

    // upgrade to vendor
    public function upgradeToVendor(Request $request)
    {
        $user = auth()->user();  

        if ($user->role === 'vendor') {
            return response()->json([
                'success' => false,
                'message' => 'You are already a vendor'
            ], 400);
        } elseif ($user->role !== 'consumer') {
            return response()->json([
                'success' => false,
                'message' => 'Only consumers can upgrade to vendors'
            ], 403);
        } elseif ($user->role === 'farmer') {
            return response()->json([
                'success' => false,
                'message' => 'You are a farmer, you cannot upgrade to vendor'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'vendor_type' => 'required|in:retailer,wholesaler',
            'address' => 'nullable|string|max:255',
            'about' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

          // Handle file upload
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        // create vendor profile
        $vendor = Vendor::create([
            'owner_id' => $user->id,
            'name' => $validated['name'],
            'vendor_type' => $validated['vendor_type'],
            'address' => $validated['address'] ?? null,
            'about' => $validated['about'] ?? null,
            'logo' => $data['logo'] ?? null,
        ]);

        $user->update([
            'role' => 'vendor',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Upgraded to vendor successfully',
            'vendor'  =>   $vendor,
            'user' => $user,
        ], 201);
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

    // Base user info
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

    if ($user->role === 'farmer' && $user->farms->first()) {
        $farm = $user->farms->first();
        $profile['farm'] = [
            'id'      => $farm->id,
            'name'    => $farm->name,
            'address' => $farm->address,
            'about'   => $farm->about,
            'status'  => $farm->status,
            'logo'    => $farm->logo,
            'cover'   => $farm->cover,
        ];
    }

    if ($user->role === 'vendor' && $user->vendors->first()) {
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




}