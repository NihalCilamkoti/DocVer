<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateRequest
{
    /**
     * Enforce Sanctum token authentication with standard JSON error on unauthenticated access.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user('sanctum')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated access. Valid Bearer token required.',
                'error_code' => 'UNAUTHENTICATED',
            ], 401);
        }

        return $next($request);
    }
}
