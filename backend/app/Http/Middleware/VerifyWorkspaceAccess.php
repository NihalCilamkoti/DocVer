<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWorkspaceAccess
{
    /**
     * Ensures authenticated user has authorized membership or ownership of the target workspace.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $workspaceId = $request->route('workspace') ?? $request->header('X-Workspace-ID') ?? $request->input('workspace_id');

        if ($workspaceId) {
            $workspace = is_numeric($workspaceId)
                ? Workspace::find($workspaceId)
                : Workspace::where('slug', $workspaceId)->first();

            if (! $workspace) {
                return response()->json(['success' => false, 'message' => 'Workspace not found.'], 404);
            }

            $isMember = $workspace->members()->where('user_id', $user->id)->exists();
            $isOwner = $workspace->owner_id === $user->id;
            $isAdmin = method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            if (! $isMember && ! $isOwner && ! $isAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden. You do not have access to this workspace.',
                    'error_code' => 'WORKSPACE_ACCESS_DENIED',
                ], 403);
            }

            // Bind current workspace to request
            $request->attributes->set('current_workspace', $workspace);
        }

        return $next($request);
    }
}
