<?php

namespace App\Jobs;

use App\Enums\ComparisonStatus;
use App\Models\ComparisonJob;
use App\Services\Comparison\ComparisonService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessComparisonJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;

    public function __construct(
        public int $comparisonJobId
    ) {}

    public function handle(ComparisonService $comparisonService): void
    {
        $job = ComparisonJob::find($this->comparisonJobId);

        if (! $job) {
            Log::warning("ComparisonJob #{$this->comparisonJobId} not found during worker execution.");
            return;
        }

        try {
            $comparisonService->executeComparisonPipeline($job);
        } catch (\Throwable $e) {
            Log::error("Comparison execution error for Job #{$job->id}: {$e->getMessage()}");
            $job->update([
                'status' => ComparisonStatus::FAILED,
                'error_message' => 'An error occurred during verification processing.',
            ]);
            throw $e;
        }
    }
}
