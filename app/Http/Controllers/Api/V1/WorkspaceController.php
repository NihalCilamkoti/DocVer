<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class WorkspaceController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspaces = $user->isSuperAdmin()
            ? Workspace::paginate(20)
            : $user->workspaces()->paginate(20);

        return $this->successResponse(
            WorkspaceResource::collection($workspaces)
        );
    }

    public function show(Workspace $workspace): JsonResponse
    {
        Gate::authorize('view', $workspace);

        return $this->successResponse(new WorkspaceResource($workspace));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['sometimes', 'in:starter,professional,enterprise'],
        ]);

        $user = $request->user();
        $workspace = Workspace::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::random(5),
            'owner_id' => $user->id,
            'plan' => $validated['plan'] ?? 'professional',
        ]);

        $workspace->members()->attach($user->id, ['role' => 'owner']);

        return $this->successResponse(new WorkspaceResource($workspace), 'Workspace created', 201);
    }

    public function update(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('update', $workspace);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'max_file_size_mb' => ['sometimes', 'integer', 'min:5', 'max:150'],
            'max_page_limit' => ['sometimes', 'integer', 'min:50', 'max:1500'],
            'retention_days' => ['sometimes', 'integer', 'min:0', 'max:90'],
            'settings' => ['sometimes', 'array'],
        ]);

        $workspace->update($validated);

        return $this->successResponse(new WorkspaceResource($workspace), 'Workspace updated');
    }
}
