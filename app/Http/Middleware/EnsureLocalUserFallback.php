<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalUserFallback
{
    /**
     * In local development, automatically resolve the default user session
     * so uploads, comparisons, and dashboard queries function seamlessly.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local') && ! $request->user()) {
            $user = User::first();
            if ($user) {
                // Ensure default workspace is attached
                if ($user->workspaces()->count() === 0) {
                    $workspace = Workspace::firstOrCreate(
                        ['owner_id' => $user->id],
                        [
                            'name' => 'Default Workspace',
                            'slug' => 'default-workspace',
                        ]
                    );
                    if (! $user->workspaces()->where('workspaces.id', $workspace->id)->exists()) {
                        $user->workspaces()->attach($workspace->id, ['role' => 'owner']);
                    }
                }

                $request->setUserResolver(fn () => $user);
                auth()->setUser($user);
            }
        }

        return $next($request);
    }
}
