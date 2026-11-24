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

class DetectSectionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $comparisonJobId
    ) {}

    public function handle(PipelineProgressTracker $tracker): void
    {
        if ($tracker->isCancelled($this->comparisonJobId)) {
            Log::info("DetectSectionsJob skipped: Job #{$this->comparisonJobId} is cancelled.");
            return;
        }

        $tracker->updateProgress($this->comparisonJobId, 'detecting_sections', 75);

        $extracted = Cache::get("docver:pipeline:job_{$this->comparisonJobId}:extracted");
        if (! $extracted) {
            return;
        }

        foreach (['A', 'B'] as $slot) {
            if (! isset($extracted[$slot])) {
                continue;
            }

            foreach ($extracted[$slot]['pages'] as &$page) {
                $detected = $this->detectSectionsInText($page['text']);
                if (! empty($detected)) {
                    $page['sections'] = array_values(array_unique(array_merge($page['sections'], $detected)));
                }
            }
        }

        Cache::put("docver:pipeline:job_{$this->comparisonJobId}:extracted", $extracted, 3600);

        Log::info("DetectSectionsJob completed for Job #{$this->comparisonJobId}");
    }

    protected function detectSectionsInText(string $text): array
    {
        $sections = [];
        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strlen($line) > 120) {
                continue;
            }

            // Match legal clause patterns: "Article 1", "Section 2.4", "Clause 10", "1.1 Definitions"
            if (preg_match('/^(?:Article|Section|Clause|\x{00A7})\s+([0-9A-Za-z\.\-]+)(?:[\.\s:\-–—]+(.*))?$/ui', $line, $m)) {
                $sections[] = $line;
            } elseif (preg_match('/^(\d+(?:\.\d+)+)\s+([A-Z][A-Za-z0-9\s,\-_]+)$/', $line, $m)) {
                $sections[] = $line;
            } elseif (preg_match('/^[A-Z0-9\s\-_]{4,50}$/', $line) && ! preg_match('/[a-z]/', $line)) {
                // ALL CAPS HEADER
                $sections[] = $line;
            }
        }

        return $sections;
    }
}
