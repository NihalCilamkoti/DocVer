<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\UsageRecord;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UsageController extends BaseApiController
{
    public function directSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspace = $user->workspaces()->first();

        if (! $workspace) {
            return $this->successResponse([
                'billing_cycle_start' => now()->startOfMonth()->toDateString(),
                'plan' => 'standard',
                'metrics' => [
                    'comparisons' => ['used' => 0, 'limit' => 250, 'percentage' => 0],
                    'pages_processed' => ['used' => 0, 'limit' => 10000, 'percentage' => 0],
                    'chat_queries' => ['used' => 0, 'limit' => -1],
                    'storage' => ['used_gb' => 0, 'limit_gb' => 50],
                ],
            ]);
        }

        return $this->summary($request, $workspace);
    }

    public function summary(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('view', $workspace);

        $startOfMonth = now()->startOfMonth();

        $comparisonsCount = UsageRecord::where('workspace_id', $workspace->id)
            ->where('metric_type', 'comparisons')
            ->where('recorded_at', '>=', $startOfMonth)
            ->sum('quantity');

        $pagesCount = UsageRecord::where('workspace_id', $workspace->id)
            ->where('metric_type', 'pages_processed')
            ->where('recorded_at', '>=', $startOfMonth)
            ->sum('quantity');

        $chatQueriesCount = UsageRecord::where('workspace_id', $workspace->id)
            ->where('metric_type', 'chat_queries')
            ->where('recorded_at', '>=', $startOfMonth)
            ->sum('quantity');

        return $this->successResponse([
            'billing_cycle_start' => $startOfMonth->toDateString(),
            'plan' => $workspace->plan,
            'metrics' => [
                'comparisons' => [
                    'used' => (int) $comparisonsCount,
                    'limit' => 250,
                    'percentage' => round(($comparisonsCount / 250) * 100, 1),
                ],
                'pages_processed' => [
                    'used' => (int) $pagesCount,
                    'limit' => 10000,
                    'percentage' => round(($pagesCount / 10000) * 100, 1),
                ],
                'chat_queries' => [
                    'used' => (int) $chatQueriesCount,
                    'limit' => -1, // Unlimited
                ],
                'storage' => [
                    'used_gb' => 2.4,
                    'limit_gb' => 50,
                ],
            ],
        ]);
    }
}
