<?php

namespace App\Services\Documents\Pipeline;

use App\Enums\ComparisonStatus;
use App\Jobs\BuildComparisonIndexJob;
use App\Jobs\ChunkDocumentJob;
use App\Jobs\CleanupTemporaryDataJob;
use App\Jobs\DetectScannedPagesJob;
use App\Jobs\DetectSectionsJob;
use App\Jobs\ExtractDocumentTextJob;
use App\Jobs\NormalizeDocumentJob;
use App\Jobs\PerformOCRJob;
use App\Jobs\ValidateUploadedFileJob;
use App\Models\ComparisonJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class DocumentPipelineCoordinator
{
    public function __construct(
        protected PipelineProgressTracker $tracker
    ) {}

    /**
     * Dispatch the full 9-stage asynchronous pipeline via Redis queue Bus chain.
     */
    public function dispatch(ComparisonJob $job): void
    {
        $jobId = $job->id;

        $job->update([
            'status' => ComparisonStatus::PROCESSING,
            'processing_progress' => 5,
            'current_stage' => 'validating',
            'started_at' => now(),
        ]);

        $this->tracker->updateProgress($jobId, 'validating', 5);

        Bus::chain([
            new ValidateUploadedFileJob($jobId),
            new ExtractDocumentTextJob($jobId),
            new DetectScannedPagesJob($jobId),
            new PerformOCRJob($jobId),
            new NormalizeDocumentJob($jobId),
            new DetectSectionsJob($jobId),
            new ChunkDocumentJob($jobId),
            new BuildComparisonIndexJob($jobId),
            new CleanupTemporaryDataJob($jobId, false), // cleanup raw staged binaries
        ])->catch(function (\Throwable $e) use ($jobId) {
            Log::error("Document pipeline failed for Job #{$jobId}: " . $e->getMessage());
            ComparisonJob::where('id', $jobId)->update([
                'status' => ComparisonStatus::FAILED,
                'error_message' => $e->getMessage(),
            ]);
            CleanupTemporaryDataJob::dispatch($jobId, true);
        })->dispatch();
    }

    /**
     * Execute pipeline synchronously in sequence (for testing or direct synchronous runs).
     */
    public function runSynchronously(ComparisonJob $job): void
    {
        $jobId = $job->id;

        $job->update([
            'status' => ComparisonStatus::PROCESSING,
            'processing_progress' => 5,
            'current_stage' => 'validating',
            'started_at' => now(),
        ]);

        $pipeline = [
            ValidateUploadedFileJob::class,
            ExtractDocumentTextJob::class,
            DetectScannedPagesJob::class,
            PerformOCRJob::class,
            NormalizeDocumentJob::class,
            DetectSectionsJob::class,
            ChunkDocumentJob::class,
            BuildComparisonIndexJob::class,
        ];

        try {
            foreach ($pipeline as $jobClass) {
                if ($this->tracker->isCancelled($jobId)) {
                    Log::info("Pipeline halted due to cancellation for Job #{$jobId}");
                    break;
                }

                $instance = app($jobClass, ['comparisonJobId' => $jobId]);
                app()->call([$instance, 'handle']);
            }

            // Cleanup raw upload files
            $cleanup = app(CleanupTemporaryDataJob::class, ['comparisonJobId' => $jobId, 'onFailure' => false]);
            app()->call([$cleanup, 'handle']);

        } catch (\Throwable $e) {
            Log::error("Synchronous pipeline failed for Job #{$jobId}: " . $e->getMessage());
            ComparisonJob::where('id', $jobId)->update([
                'status' => ComparisonStatus::FAILED,
                'error_message' => $e->getMessage(),
            ]);
            $cleanup = app(CleanupTemporaryDataJob::class, ['comparisonJobId' => $jobId, 'onFailure' => true]);
            app()->call([$cleanup, 'handle']);
            throw $e;
        }
    }
}
