<?php

namespace App\Http\Middleware;

use App\Models\DocumentMetadata;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDocumentOwnership
{
    /**
     * Prevents IDOR access to documents across different workspaces.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $docId = $request->route('document') ?? $request->input('document_id');

        if ($docId && $user) {
            $document = DocumentMetadata::find($docId);

            if (! $document) {
                return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
            }

            $workspace = $document->workspace;
            if ($workspace) {
                $isMember = $workspace->members()->where('user_id', $user->id)->exists();
                $isOwner = $workspace->owner_id === $user->id;
                $isAdmin = method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

                if (! $isMember && ! $isOwner && ! $isAdmin) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied to target document.',
                        'error_code' => 'DOCUMENT_ACCESS_DENIED',
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
