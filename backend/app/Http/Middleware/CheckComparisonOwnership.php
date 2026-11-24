<?php

namespace App\Http\Middleware;

use App\Models\ComparisonJob;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckComparisonOwnership
{
    /**
     * Prevents IDOR access to comparison jobs and reports across tenant boundaries.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $jobId = $request->route('comparison') ?? $request->route('job') ?? $request->input('comparison_job_id');

        if ($jobId && $user) {
            $job = ComparisonJob::find($jobId);

            if (! $job) {
                return response()->json(['success' => false, 'message' => 'Comparison job not found.'], 404);
            }

            $workspace = $job->workspace;
            if ($workspace) {
                $isMember = $workspace->members()->where('user_id', $user->id)->exists();
                $isOwner = $workspace->owner_id === $user->id;
                $isAdmin = method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

                if (! $isMember && ! $isOwner && ! $isAdmin) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied to target comparison job.',
                        'error_code' => 'COMPARISON_ACCESS_DENIED',
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
