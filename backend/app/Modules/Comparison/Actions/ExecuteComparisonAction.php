<?php

namespace App\Modules\Comparison\Actions;

use App\Models\ComparisonJob;
use App\Services\Comparison\ComparisonOrchestrator;
use App\Services\Comparison\DTOs\ComparisonResultDTO;

class ExecuteComparisonAction
{
    public function __construct(
        protected ComparisonOrchestrator $orchestrator
    ) {}

    /**
     * Execute full intelligent comparison pipeline across Document A and Document B.
     */
    public function execute(ComparisonJob $job): ComparisonResultDTO
    {
        return $this->orchestrator->compare($job);
    }
}
