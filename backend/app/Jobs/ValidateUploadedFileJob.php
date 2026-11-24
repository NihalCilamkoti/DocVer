<?php

namespace App\Jobs;

use App\Exceptions\SecurityException;
use App\Models\ComparisonJob;
use App\Services\Documents\Contracts\MalwareScannerInterface;
use App\Services\Documents\Pipeline\PipelineProgressTracker;
use App\Services\Documents\Security\FileSignatureValidator;
use App\Services\Documents\Security\ZipBombGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ValidateUploadedFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $comparisonJobId
    ) {}

    public function handle(
        FileSignatureValidator $validator,
        ZipBombGuard $zipGuard,
        MalwareScannerInterface $malwareScanner,
        PipelineProgressTracker $tracker
    ): void {
        if ($tracker->isCancelled($this->comparisonJobId)) {
            Log::info("ValidateUploadedFileJob skipped: Job #{$this->comparisonJobId} is cancelled.");
            return;
        }

        $tracker->updateProgress($this->comparisonJobId, 'validating', 10);

        $job = ComparisonJob::with(['documentA', 'documentB'])->findOrFail($this->comparisonJobId);

        $documents = array_filter([$job->documentA, $job->documentB]);
        if (count($documents) < 2) {
            throw new \RuntimeException("Comparison Job #{$this->comparisonJobId} requires exactly two documents for comparison.");
        }

        foreach ($documents as $doc) {
            $fullPath = Storage::disk('local')->path($doc->storage_path);

            if (! file_exists($fullPath)) {
                throw new \RuntimeException("Uploaded document missing from temporary storage: {$doc->original_name}");
            }

            // 1. Validate magic bytes file signature (never trust client MIME or extension)
            $sigResult = $validator->validate($fullPath);
            if (! $sigResult->isValid) {
                throw new \SecurityException("Security verification failed for {$doc->original_name}: {$sigResult->errorMessage}");
            }

            // 2. ZIP Bomb protection for container formats (DOCX, XLSX, PPTX)
            if ($zipGuard->isZipArchive($fullPath)) {
                $zipGuard->assertSafe($fullPath);
            }

            // 3. Malware and macro heuristic scan
            $malwareScanner->scan($fullPath);
        }

        Log::info("ValidateUploadedFileJob passed for Job #{$this->comparisonJobId}");
    }
}
