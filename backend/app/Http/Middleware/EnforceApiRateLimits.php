<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class EnforceApiRateLimits
{
    /**
     * Sliding window rate limiter for API endpoints (e.g. 120 req/min).
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 120, int $decaySeconds = 60): Response
    {
        $key = 'api_rate:' . ($request->user()?->id ?? $request->ip());

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => "Too many API requests. Retry in {$retryAfter} seconds.",
                'error_code' => 'RATE_LIMIT_EXCEEDED',
            ], 429)->header('Retry-After', $retryAfter);
        }

        RateLimiter::hit($key, $decaySeconds);

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($key, $maxAttempts));

        return $response;
    }
}
