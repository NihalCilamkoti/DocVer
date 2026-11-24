<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ComparisonReportResource;
use App\Models\ComparisonJob;
use App\Services\Reports\PdfReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ReportController extends BaseApiController
{
    public function show(ComparisonJob $job): JsonResponse
    {
        Gate::authorize('view', $job);

        $report = $job->report()->with('findings')->first();

        if (! $report) {
            return $this->errorResponse('Comparison report is still generating or not found.', [], 404);
        }

        return $this->successResponse(new ComparisonReportResource($report));
    }

    public function downloadPdf(ComparisonJob $job, PdfReportService $pdfService): Response|JsonResponse
    {
        Gate::authorize('view', $job);

        $report = $job->report()->with('findings')->first();

        if (! $report) {
            return $this->errorResponse('Comparison report is not ready for download.', [], 404);
        }

        return $pdfService->generatePdfResponse($job, $report);
    }
}
