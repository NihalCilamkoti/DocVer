<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    /**
     * Restricts endpoint to authenticated super administrators.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'isSuperAdmin') || ! $user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Access restricted to system administrators.',
                'error_code' => 'ADMIN_ACCESS_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}
