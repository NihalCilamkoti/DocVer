<?php

namespace App\Jobs;

use App\Models\ComparisonJob;
use App\Services\Documents\Indexing\EphemeralIndexManager;
use App\Services\Documents\Pipeline\PipelineProgressTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BuildComparisonIndexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $comparisonJobId
    ) {}

    public function handle(
        EphemeralIndexManager $indexManager,
        PipelineProgressTracker $tracker
    ): void {
        if ($tracker->isCancelled($this->comparisonJobId)) {
            Log::info("BuildComparisonIndexJob skipped: Job #{$this->comparisonJobId} is cancelled.");
            return;
        }

        $tracker->updateProgress($this->comparisonJobId, 'indexing', 95);

        $chunkMap = Cache::get("docver:pipeline:job_{$this->comparisonJobId}:chunks");
        if (! $chunkMap) {
            throw new \RuntimeException("Chunk cache missing for Job #{$this->comparisonJobId}");
        }

        $totalChunks = 0;
        $totalTokens = 0;

        foreach (['A', 'B'] as $slot) {
            $chunks = $chunkMap[$slot] ?? [];
            $indexManager->storeChunks($this->comparisonJobId, $slot, $chunks);

            $totalChunks += count($chunks);
            foreach ($chunks as $chunk) {
                $totalTokens += $chunk->tokenCount;
            }
        }

        $indexManager->storeIndexMetadata($this->comparisonJobId, [
            'total_chunks' => $totalChunks,
            'total_tokens' => $totalTokens,
            'slots' => array_keys($chunkMap),
            'indexed_at' => now()->toIso8601String(),
        ]);

        // Mark processing progress as 100% completed
        $tracker->updateProgress($this->comparisonJobId, 'completed', 100, [
            'total_chunks' => $totalChunks,
            'total_tokens' => $totalTokens,
        ]);

        // Clean up raw extracted intermediate memory
        Cache::forget("docver:pipeline:job_{$this->comparisonJobId}:extracted");
        Cache::forget("docver:pipeline:job_{$this->comparisonJobId}:chunks");

        Log::info("BuildComparisonIndexJob completed for Job #{$this->comparisonJobId}: indexed {$totalChunks} chunks ephemerally.");
    }
}
