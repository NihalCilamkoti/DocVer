<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Ensures authenticated user account is in an active status (not suspended or deactivated).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && isset($user->is_active) && ! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account has been deactivated. Please contact support.',
                'error_code' => 'ACCOUNT_DEACTIVATED',
            ], 403);
        }

        return $next($request);
    }
}
