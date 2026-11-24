<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ComparisonJobResource;
use App\Models\ComparisonJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class JobController extends BaseApiController
{
    public function show(ComparisonJob $job): JsonResponse
    {
        Gate::authorize('view', $job);

        return $this->successResponse(
            new ComparisonJobResource($job->load(['documentA', 'documentB', 'report']))
        );
    }
}
