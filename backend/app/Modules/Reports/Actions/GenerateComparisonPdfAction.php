<?php

namespace App\Modules\Reports\Actions;

use App\Models\ComparisonJob;
use App\Services\Reports\ReportExportService;

class GenerateComparisonPdfAction
{
    public function __construct(
        protected ReportExportService $exportService
    ) {}

    /**
     * Generate verifiable executive PDF report with findings and audit trails.
     */
    public function execute(ComparisonJob $job): string
    {
        return $this->exportService->generatePdfReport($job);
    }
}
