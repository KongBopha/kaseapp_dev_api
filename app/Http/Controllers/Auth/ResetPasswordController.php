<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Mail\ResetPasswordMail;

class ResetPasswordController extends Controller
{
    public function sendResetCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        // Generate 6-digit OTP
        $code = rand(100000, 999999);

        $user->update([
            'reset_code' => $code,
            'reset_code_expires_at' => now()->addMinutes(10),
        ]);

        // Send email
        Mail::to($user->email)->send(new ResetPasswordMail($code));

        return response()->json([
            'success' => true,
            'message' => 'Reset code sent to your email.'
        ], 200);
    }

    // Step 2: Verify code
    public function verifyCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'reset_code' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)
                    ->where('reset_code', $request->reset_code)
                    ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset code.'
            ], 400);
        }

        if ($user->reset_code_expires_at < now()) {
            return response()->json([
                'success' => false,
                'message' => 'Reset code has expired.'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Code verified. You can reset your password.'
        ], 200);
    }

    // Step 3: Reset password
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'reset_code' => 'required|numeric',
            'password' => 'required|string|confirmed|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)
                    ->where('reset_code', $request->reset_code)
                    ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset code.'
            ], 400);
        }

        if ($user->reset_code_expires_at < now()) {
            return response()->json([
                'success' => false,
                'message' => 'Reset code has expired.'
            ], 400);
        }

        // Update password & clear reset code
        $user->update([
            'password' => Hash::make($request->password),
            'reset_code' => null,
            'reset_code_expires_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.'
        ], 200);
    }
}
