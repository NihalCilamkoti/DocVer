<?php

namespace App\Jobs;

use App\Models\ComparisonJob;
use App\Services\Documents\Contracts\OCRServiceInterface;
use App\Services\Documents\Pipeline\PipelineProgressTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PerformOCRJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $comparisonJobId
    ) {}

    public function handle(
        OCRServiceInterface $ocrService,
        PipelineProgressTracker $tracker
    ): void {
        if ($tracker->isCancelled($this->comparisonJobId)) {
            Log::info("PerformOCRJob skipped: Job #{$this->comparisonJobId} is cancelled.");
            return;
        }

        $tracker->updateProgress($this->comparisonJobId, 'ocr_processing', 50);

        $extracted = Cache::get("docver:pipeline:job_{$this->comparisonJobId}:extracted");
        if (! $extracted) {
            return;
        }

        $job = ComparisonJob::with(['documentA', 'documentB'])->find($this->comparisonJobId);

        foreach (['A' => $job?->documentA, 'B' => $job?->documentB] as $slot => $doc) {
            if (! isset($extracted[$slot]) || ! $doc) {
                continue;
            }

            $ext = strtolower(pathinfo($doc->original_name, PATHINFO_EXTENSION));
            $isImageFormat = in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'tiff', 'tif']);

            if ($isImageFormat) {
                $fullPath = Storage::disk('local')->path($doc->storage_path);
                $pageDto = $ocrService->extractTextFromImage($fullPath, 1);

                $extracted[$slot]['pages'] = [[
                    'pageNumber' => 1,
                    'text' => $pageDto->text,
                    'tables' => [],
                    'sections' => [],
                    'isScanned' => true,
                    'wordCount' => $pageDto->wordCount,
                ]];
                $extracted[$slot]['totalWordCount'] = $pageDto->wordCount;
            }
        }

        Cache::put("docver:pipeline:job_{$this->comparisonJobId}:extracted", $extracted, 3600);

        Log::info("PerformOCRJob completed for Job #{$this->comparisonJobId}");
    }
}
