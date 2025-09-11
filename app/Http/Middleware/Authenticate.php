<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;

class Authenticate
{
    public function handle(Request $request, Closure $next, ...$guards)
    {
        // Default to sanctum if no guard specified
        $guards = empty($guards) ? ['sanctum'] : $guards;

        foreach ($guards as $guard) {
            if (auth()->guard($guard)->check()) {
                return $next($request);
            }
        }

        return $this->unauthenticated($request);
    }

    protected function unauthenticated($request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
        ], 401);
    }
}
