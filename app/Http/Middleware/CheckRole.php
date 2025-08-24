<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $role
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $role): Response
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized, please login first'
            ], 401);
        }

        if ($user->role !== $role) {
            $message = match ($role) {
                'vendor' => 'Please register as a vendor first to access this feature.',
                'farmer' => 'Please register as a farmer first to access this feature.',
                default => 'Unauthorized, role mismatch',
            };

            return response()->json([
                'success' => false,
                'message' => $message
            ], 403);
        }

        return $next($request);
    }
}

