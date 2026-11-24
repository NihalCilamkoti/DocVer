<?php

namespace App\Jobs;

use App\Models\ComparisonJob;
use App\Services\Documents\Indexing\EphemeralIndexManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupTemporaryDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $comparisonJobId,
        public bool $onFailureOrCancel = false
    ) {}

    public function handle(EphemeralIndexManager $indexManager): void
    {
        Log::info("CleanupTemporaryDataJob running for Job #{$this->comparisonJobId} (fail/cancel={$this->onFailureOrCancel})");

        $job = ComparisonJob::with(['documentA', 'documentB'])->find($this->comparisonJobId);

        // Purge raw staged upload files from disk once indexed (Zero Permanent Content Storage guarantee) or on failure/cancel
        if ($job) {
            $docs = collect([$job->documentA, $job->documentB])
                ->filter()
                ->merge(\App\Models\DocumentMetadata::where('comparison_job_id', $this->comparisonJobId)->get())
                ->unique('id');

            foreach ($docs as $doc) {
                if ($doc && !empty($doc->storage_path) && Storage::disk('local')->exists($doc->storage_path)) {
                    Storage::disk('local')->delete($doc->storage_path);
                }
            }
        }

        // If job failed or cancelled, clear ephemeral indexes and cached pipeline state
        if ($this->onFailureOrCancel) {
            $indexManager->clearIndex($this->comparisonJobId);
            Cache::forget("docver:pipeline:job_{$this->comparisonJobId}:extracted");
            Cache::forget("docver:pipeline:job_{$this->comparisonJobId}:chunks");
            Cache::forget("docver:pipeline:job_{$this->comparisonJobId}:status");
        }
    }
}
