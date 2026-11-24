<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionLimits
{
    /**
     * Enforce workspace tier monthly limits on comparison volume.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->attributes->get('current_workspace');

        if ($workspace) {
            $month = now()->format('Y-m');
            $key = "docver:usage:ws_{$workspace->id}:month_{$month}:comparisons";
            $used = (int) Cache::get($key, 0);

            $limit = match ($workspace->plan ?? 'starter') {
                'starter' => 50,
                'professional' => 250,
                'enterprise' => 5000,
                default => 50,
            };

            if ($used >= $limit && ! $request->isMethod('GET')) {
                return response()->json([
                    'success' => false,
                    'message' => "Monthly comparison quota exceeded ({$used}/{$limit} used). Upgrade plan to proceed.",
                    'error_code' => 'SUBSCRIPTION_LIMIT_EXCEEDED',
                ], 429);
            }
        }

        return $next($request);
    }
}
