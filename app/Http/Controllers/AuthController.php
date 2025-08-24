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


class AuthController extends Controller
{
    
    public function store(Request $request)
    {
        // Validate input
        $validated = $request->validate([
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

        // validate email or phone
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
                'role' => 'consumer', // default role
                'password' => Hash::make($validated['password']),
            ]);

            // Generate token
            $token = $user->createToken(
                'auth_token',
                ['*'], 
                now()->addMinutes(60) // expires in 60 minutes
            )->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registered successfully',
                'access_token' => $token,
                'user' => $user
            ], 201); 

        } catch (\Exception $e) {
            // Catch DB or unexpected errors
            return response()->json([
                'success' => false,
                'message' => 'User creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    // login
    public function login(Request $request)
    {
        $executed = RateLimiter::attempt(
            'login-attempt:' . $request->ip(),
            $perMinute = 5,
            function () {}
        );

        if (!$executed) {
            return response()->json([
                'success' => false,
                'message' => 'Too many login attempts. Please try again later.',
                'redirect' => route('login'),
            ], 429);
        }

        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['login'])
            ->orWhere('phone', $validated['login'])
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ], 200);
    }

    // get current user 
    public function me(Request $request)
    {
        $user = Auth::user();
        return response()->json([
            'success' => true,
            'name' => $user->first_name . ' ' . $user->last_name,
            'email' => $user->email,
            'role' => $user->role
        ], 200); 
    }

    // logout user
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ], 200);
    }

    // refresh token 

    public function refresh(Request $request){

        $user= Auth::user();
        $request->user()->currentAccessToken()->delete();
        $newToken = $user->createToken('auth_token')->plainTextToken;

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
        $user = auth()->user();//logged in user

        if($user->role ==='farmer'){
            return response()->json([
                'success' => false,
                'message' => 'You are already a farmer'
            ], 201);
        }
        
        elseif ($user->role !== 'consumer') {
            return response()->json([
                'success' => false,
                'message' => 'Only consumers can upgrade to farmers'
            ], 403);
        }
        elseif($user->role==='vendor'){
            return response()->json([
                'success' => false,
                'message' => 'You are a vendor, you cannot upgrade to farmer'
            ], 403);
        }
        else{
        // validate farmer registration data

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'about' => 'nullable|string',
            'cover'=> 'nullable|image|max:2048', 
            'logo'=> 'nullable|image|max:2048',
        ]);

        // create farmer profile

        $farmer = Farm::create([
            'owner_id' => $user->id,
            'name' => $validated['name'],
            'address' => $validated['address'],
            'description' => $validated['about'] ?? null,
            'cover' => $validated['cover'] ?? null,
            'logo' => $validated['logo'] ?? null,
        ]);
        
        $user->update([
            'role' => 'farmer',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Upgraded to farmer successfully',
            'farmer' => $farmer,
            'user' => $user,
            'role'=> $user->role
        ], 201);
        }
    }

    // upgrade to vendor
    public function upgradeToVendor(Request $request)
    {
        $user = auth()->user();//logged in user

        if($user->role ==='vendor'){
            return response()->json([
                'success' => false,
                'message' => 'You are already a vendor'
            ], 201);
        }
        
        elseif ($user->role !== 'consumer') {
            return response()->json([
                'success' => false,
                'message' => 'Only consumers can upgrade to vendors'
            ], 403);
        }
        elseif($user->role==='farmer'){
            return response()->json([
                'success' => false,
                'message' => 'You are a farmer, you cannot upgrade to vendor'
            ], 403);
        }
        else{
        
         // validate vendor registration data

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'vendor_type' => 'required|in:retailer,wholesaler',
            'address' => 'nullable|string|max:255',
            'about' => 'nullable|string',
            'logo'=> 'nullable|image|max:2048',
        ]);

        // create vendor profile

        $vendor = Vendor::create([
            'owner_id' => $user->id,
            'name' => $validated['name'],
            'vendor_type' => $validated['vendor_type'],
            'address' => $validated['address'],
            'about' => $validated['about'] ?? null,
            'logo' => $validated['logo'] ?? null,
        ]);
        
        $user->update([
            'role' => 'vendor',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Upgraded to vendor successfully',
            'vendor' => $vendor,
            'user' => $user,
            'role'=> $user->role
        ], 201);
        }
    }
}
