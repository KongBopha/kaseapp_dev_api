<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Sanctum\PersonalAccessToken;

class CheckAccessToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tokenString = $request->bearerToken();

        if (!$tokenString) {
            return response()->json(['success' => false, 'message' => 'Access token required'], 401);
        }

        $token = PersonalAccessToken::findToken($tokenString);

        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Invalid access token'], 401);
        }

        // Check if token has expired
        if ($token->expires_at && $token->expires_at->isPast()) {
            $token->delete(); // Revoke expired token
            return response()->json(['success' => false, 'message' => 'Access token expired'], 401);
        }

        // Properly set the authenticated user
        auth()->login($token->tokenable);

        return $next($request);
    }

}
