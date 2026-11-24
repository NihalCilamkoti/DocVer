<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\CreateComparisonDTO;
use App\Http\Requests\CreateComparisonRequest;
use App\Http\Resources\ComparisonJobResource;
use App\Jobs\CleanupTemporaryDataJob;
use App\Models\ComparisonJob;
use App\Models\Workspace;
use App\Services\Comparison\ComparisonService;
use App\Services\Documents\Pipeline\PipelineProgressTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComparisonController extends BaseApiController
{
    public function __construct(
        protected ComparisonService $comparisonService,
        protected PipelineProgressTracker $progressTracker
    ) {}

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('view', $workspace);

        $jobs = $workspace->comparisonJobs()
            ->with(['documentA', 'documentB', 'report'])
            ->latest()
            ->paginate(15);

        return $this->successResponse(
            ComparisonJobResource::collection($jobs)
        );
    }

    /**
     * Direct index endpoint for current authenticated user's workspace
     */
    public function directIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspace = $user->workspaces()->first();

        if (! $workspace) {
            return $this->successResponse([]);
        }

        $jobs = $workspace->comparisonJobs()
            ->with(['documentA', 'documentB', 'report.findings'])
            ->latest()
            ->paginate(15);

        return $this->successResponse(
            ComparisonJobResource::collection($jobs)
        );
    }

    public function store(CreateComparisonRequest $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('view', $workspace);

        $dto = CreateComparisonDTO::fromArray($request->validated(), $workspace->id);
        $job = $this->comparisonService->createJob($workspace, $request->user(), $dto);

        return $this->successResponse(
            new ComparisonJobResource($job),
            'Comparison job queued for processing',
            202
        );
    }

    /**
     * Direct store endpoint resolving user's workspace automatically
     */
    public function directStore(CreateComparisonRequest $request): JsonResponse
    {
        $user = $request->user();
        $workspace = $user->workspaces()->first();

        if (! $workspace) {
            $workspace = Workspace::create([
                'name' => 'Default Workspace',
                'slug' => 'workspace-' . $user->id,
                'owner_id' => $user->id,
            ]);
            $user->workspaces()->attach($workspace->id, ['role' => 'owner']);
        }

        return $this->store($request, $workspace);
    }

    public function show(Workspace $workspace, ComparisonJob $comparison): JsonResponse
    {
        Gate::authorize('view', $comparison);

        return $this->successResponse(
            new ComparisonJobResource($comparison->load(['documentA', 'documentB', 'report.findings']))
        );
    }

    /**
     * Direct show endpoint resolving comparison directly by model binding
     */
    public function directShow(Request $request, ComparisonJob $comparison): JsonResponse
    {
        Gate::authorize('view', $comparison);

        return $this->successResponse(
            new ComparisonJobResource($comparison->load(['documentA', 'documentB', 'report.findings']))
        );
    }

    /**
     * Polling or SSE progress tracking endpoint: GET /api/v1/comparisons/{comparison}/status
     */
    public function status(Request $request, ComparisonJob $comparison): JsonResponse|StreamedResponse
    {
        Gate::authorize('view', $comparison);

        // Check if SSE stream requested
        $wantsStream = $request->header('Accept') === 'text/event-stream' || $request->boolean('stream');

        if ($wantsStream) {
            return response()->stream(function () use ($comparison) {
                // Send initial state
                $data = $this->progressTracker->getProgress($comparison->id);
                echo "data: " . json_encode($data) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        $progress = $this->progressTracker->getProgress($comparison->id);

        return $this->successResponse($progress, 'Comparison processing status');
    }

    /**
     * Cancel ongoing comparison job: POST /api/v1/comparisons/{comparison}/cancel
     */
    public function cancel(Request $request, ComparisonJob $comparison): JsonResponse
    {
        Gate::authorize('update', $comparison);

        $this->progressTracker->cancel($comparison->id);

        // Immediate cleanup of ephemeral and temporary data
        CleanupTemporaryDataJob::dispatch($comparison->id, true);

        return $this->successResponse([
            'job_id' => $comparison->id,
            'status' => 'cancelled',
            'cancelled_at' => now()->toIso8601String(),
        ], 'Comparison job successfully cancelled.');
    }
}
