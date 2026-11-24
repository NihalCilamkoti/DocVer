<?php

namespace App\Jobs;

use App\Services\Documents\DTOs\ExtractedPageDTO;
use App\Services\Documents\DTOs\ExtractedTableDTO;
use App\Services\Documents\OCR\ScannedPageDetector;
use App\Services\Documents\Pipeline\PipelineProgressTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DetectScannedPagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $comparisonJobId
    ) {}

    public function handle(
        ScannedPageDetector $detector,
        PipelineProgressTracker $tracker
    ): void {
        if ($tracker->isCancelled($this->comparisonJobId)) {
            Log::info("DetectScannedPagesJob skipped: Job #{$this->comparisonJobId} is cancelled.");
            return;
        }

        $tracker->updateProgress($this->comparisonJobId, 'detecting_scans', 40);

        $extracted = Cache::get("docver:pipeline:job_{$this->comparisonJobId}:extracted");
        if (! $extracted) {
            throw new \RuntimeException("Extracted document cache missing for Job #{$this->comparisonJobId}");
        }

        foreach (['A', 'B'] as $slot) {
            if (! isset($extracted[$slot])) {
                continue;
            }

            foreach ($extracted[$slot]['pages'] as &$p) {
                $pageDTO = new ExtractedPageDTO(
                    pageNumber: $p['pageNumber'],
                    text: $p['text'],
                    tables: array_map(fn($t) => new ExtractedTableDTO($t['headers'], $t['rows']), $p['tables']),
                    sections: $p['sections'],
                    isScanned: $p['isScanned'],
                    wordCount: $p['wordCount']
                );

                if ($detector->isScanned($pageDTO)) {
                    $p['isScanned'] = true;
                }
            }
        }

        Cache::put("docver:pipeline:job_{$this->comparisonJobId}:extracted", $extracted, 3600);

        Log::info("DetectScannedPagesJob completed for Job #{$this->comparisonJobId}");
    }
}
