<?php

namespace App\Http\Middleware;

use App\Services\AI\Quota\AIQuotaManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDailyUsageLimits
{
    /**
     * Enforces daily per-user token and request limits before issuing expensive operations.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $today = now()->format('Y-m-d');
            $userKey = "docver:quota:user_{$user->id}:date_{$today}:requests";
            $requestsToday = (int) \Illuminate\Support\Facades\Cache::get($userKey, 0);

            $dailyLimit = 2000;
            if ($requestsToday >= $dailyLimit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Daily user AI verification limit reached. Resets at 00:00 UTC.',
                    'error_code' => 'DAILY_USAGE_LIMIT_EXCEEDED',
                ], 429);
            }
        }

        return $next($request);
    }
}
