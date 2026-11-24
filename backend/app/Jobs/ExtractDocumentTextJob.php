<?php

namespace App\Jobs;

use App\Models\ComparisonJob;
use App\Services\Documents\Parsers\DocumentParserFactory;
use App\Services\Documents\Pipeline\PipelineProgressTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExtractDocumentTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $comparisonJobId
    ) {}

    public function handle(
        DocumentParserFactory $parserFactory,
        PipelineProgressTracker $tracker
    ): void {
        if ($tracker->isCancelled($this->comparisonJobId)) {
            Log::info("ExtractDocumentTextJob skipped: Job #{$this->comparisonJobId} is cancelled.");
            return;
        }

        $tracker->updateProgress($this->comparisonJobId, 'extracting', 25);

        $job = ComparisonJob::with(['documentA', 'documentB'])->findOrFail($this->comparisonJobId);

        $extracted = [];

        foreach (['A' => $job->documentA, 'B' => $job->documentB] as $slot => $doc) {
            $fullPath = Storage::disk('local')->path($doc->storage_path);
            $ext = pathinfo($doc->original_name, PATHINFO_EXTENSION);

            $parser = $parserFactory->getParser($ext);
            $docDTO = $parser->parse($fullPath);

            // Update document page count metadata in DB without storing text in DB
            $doc->update([
                'page_count' => $docDTO->totalPages,
            ]);

            // Convert to array for ephemeral Redis/cache pipeline transfer
            $extracted[$slot] = [
                'originalName' => $docDTO->originalName,
                'format' => $docDTO->format,
                'totalPages' => $docDTO->totalPages,
                'totalWordCount' => $docDTO->totalWordCount,
                'pages' => array_map(function ($page) {
                    return [
                        'pageNumber' => $page->pageNumber,
                        'text' => $page->text,
                        'tables' => array_map(fn($t) => [
                            'headers' => $t->headers,
                            'rows' => $t->rows,
                        ], $page->tables),
                        'sections' => $page->sections,
                        'isScanned' => $page->isScanned,
                        'wordCount' => $page->wordCount,
                    ];
                }, $docDTO->pages),
                'metadata' => $docDTO->metadata,
            ];
        }

        // Ephemeral pipeline storage (TTL 1 hour)
        Cache::put("docver:pipeline:job_{$this->comparisonJobId}:extracted", $extracted, 3600);

        Log::info("ExtractDocumentTextJob completed for Job #{$this->comparisonJobId}");
    }
}
