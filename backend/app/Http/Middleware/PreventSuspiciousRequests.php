<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventSuspiciousRequests
{
    /**
     * Blocks obvious SQL injection probes, directory traversal, and malicious request patterns.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $uri = urldecode($request->getRequestUri());

        // Path traversal patterns
        if (str_contains($uri, '../') || str_contains($uri, '..\\') || str_contains($uri, '/etc/passwd')) {
            return response()->json([
                'success' => false,
                'message' => 'Suspicious request pattern rejected.',
                'error_code' => 'SECURITY_VIOLATION',
            ], 403);
        }

        return $next($request);
    }
}
