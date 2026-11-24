<?php

namespace App\Jobs;

use App\Models\ComparisonJob;
use App\Services\Documents\DTOs\ExtractedDocumentDTO;
use App\Services\Documents\DTOs\ExtractedPageDTO;
use App\Services\Documents\DTOs\ExtractedTableDTO;
use App\Services\Documents\Indexing\DocumentChunker;
use App\Services\Documents\Pipeline\PipelineProgressTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChunkDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $comparisonJobId
    ) {}

    public function handle(
        DocumentChunker $chunker,
        PipelineProgressTracker $tracker
    ): void {
        if ($tracker->isCancelled($this->comparisonJobId)) {
            Log::info("ChunkDocumentJob skipped: Job #{$this->comparisonJobId} is cancelled.");
            return;
        }

        $tracker->updateProgress($this->comparisonJobId, 'chunking', 85);

        $extracted = Cache::get("docver:pipeline:job_{$this->comparisonJobId}:extracted");
        if (! $extracted) {
            throw new \RuntimeException("Extracted document cache missing for Job #{$this->comparisonJobId}");
        }

        $job = ComparisonJob::with(['documentA', 'documentB'])->findOrFail($this->comparisonJobId);

        $chunkMap = [];

        foreach (['A' => $job->documentA, 'B' => $job->documentB] as $slot => $doc) {
            if (! isset($extracted[$slot]) || ! $doc) {
                continue;
            }

            $rawPages = $extracted[$slot]['pages'];
            $pageDTOs = array_map(function ($p) {
                return new ExtractedPageDTO(
                    pageNumber: $p['pageNumber'],
                    text: $p['text'],
                    tables: array_map(fn($t) => new ExtractedTableDTO($t['headers'], $t['rows']), $p['tables']),
                    sections: $p['sections'],
                    isScanned: $p['isScanned'],
                    wordCount: $p['wordCount']
                );
            }, $rawPages);

            $docDTO = new ExtractedDocumentDTO(
                originalName: $extracted[$slot]['originalName'],
                format: $extracted[$slot]['format'],
                totalPages: $extracted[$slot]['totalPages'],
                pages: $pageDTOs,
                metadata: $extracted[$slot]['metadata'],
                totalWordCount: $extracted[$slot]['totalWordCount']
            );

            $chunks = $chunker->chunk($docDTO, $doc->id, $slot);
            $chunkMap[$slot] = $chunks;
        }

        // Cache the chunk objects for the indexing job
        Cache::put("docver:pipeline:job_{$this->comparisonJobId}:chunks", $chunkMap, 3600);

        Log::info("ChunkDocumentJob completed for Job #{$this->comparisonJobId} (Slot A: " . count($chunkMap['A'] ?? []) . " chunks, Slot B: " . count($chunkMap['B'] ?? []) . " chunks)");
    }
}
