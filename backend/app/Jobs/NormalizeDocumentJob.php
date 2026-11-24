<?php

namespace App\Jobs;

use App\Services\Documents\Pipeline\PipelineProgressTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NormalizeDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $comparisonJobId
    ) {}

    public function handle(PipelineProgressTracker $tracker): void
    {
        if ($tracker->isCancelled($this->comparisonJobId)) {
            Log::info("NormalizeDocumentJob skipped: Job #{$this->comparisonJobId} is cancelled.");
            return;
        }

        $tracker->updateProgress($this->comparisonJobId, 'normalizing', 65);

        $extracted = Cache::get("docver:pipeline:job_{$this->comparisonJobId}:extracted");
        if (! $extracted) {
            return;
        }

        foreach (['A', 'B'] as $slot) {
            if (! isset($extracted[$slot])) {
                continue;
            }

            foreach ($extracted[$slot]['pages'] as &$page) {
                $page['text'] = $this->normalizeText($page['text']);
                $page['wordCount'] = str_word_count($page['text']);
            }
        }

        Cache::put("docver:pipeline:job_{$this->comparisonJobId}:extracted", $extracted, 3600);

        Log::info("NormalizeDocumentJob completed for Job #{$this->comparisonJobId}");
    }

    protected function normalizeText(string $text): string
    {
        // 1. Remove non-printable control characters except newline and tab
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        // 2. Normalize Windows/Mac line endings to standard Unix newline
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // 3. Normalize multiple contiguous horizontal whitespaces to single space
        $text = preg_replace('/[ \t]+/', ' ', $text);

        // 4. Normalize excessive newlines (more than 2 consecutive) to double newline
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // 5. Normalize smart quotes and dashes to standard Unicode
        $search = ["\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x94", "\xE2\x80\x93"];
        $replace = ['"', '"', "'", "'", " -- ", " - "];
        $text = str_replace($search, $replace, $text);

        return trim($text);
    }
}
