<?php

namespace App\Services\Documents\Pipeline;

use App\Enums\ComparisonStatus;
use App\Models\ComparisonJob;
use Illuminate\Support\Facades\Cache;

class PipelineProgressTracker
{
    public const STAGES = [
        'uploaded' => ['label' => 'Documents uploaded and checksum verified', 'progress' => 5],
        'validating' => ['label' => 'Validating file integrity and security signatures', 'progress' => 10],
        'extracting' => ['label' => 'Extracting document text and layout boundaries', 'progress' => 20],
        'ocr_processing' => ['label' => 'Executing OCR on scanned documents', 'progress' => 30],
        'indexing' => ['label' => 'Building ephemeral citation chunks and index', 'progress' => 45],
        'matching_sections' => ['label' => 'Matching equivalent document sections and clauses', 'progress' => 60],
        'comparing' => ['label' => 'Performing clause and numerical discrepancy analysis', 'progress' => 75],
        'validating_evidence' => ['label' => 'Validating candidate findings against source text', 'progress' => 90],
        'generating_report' => ['label' => 'Generating final structured audit report', 'progress' => 95],
        'completed' => ['label' => 'Comparison completed successfully', 'progress' => 100],
        'failed' => ['label' => 'Processing failed', 'progress' => 0],
        'cancelled' => ['label' => 'Processing cancelled by user', 'progress' => 0],
    ];

    public function updateProgress(int $jobId, string $stage, ?int $overrideProgress = null, array $meta = []): void
    {
        $stageInfo = self::STAGES[$stage] ?? ['label' => $stage, 'progress' => 50];
        $percent = $overrideProgress ?? $stageInfo['progress'];

        $status = match(true) {
            $stage === 'completed' || $percent >= 100 => ComparisonStatus::COMPLETED,
            $stage === 'failed' => ComparisonStatus::FAILED,
            $stage === 'cancelled' => ComparisonStatus::CANCELLED,
            default => ComparisonStatus::PROCESSING,
        };

        $data = [
            'job_id' => $jobId,
            'stage' => $stage,
            'current_stage' => $stage,
            'status' => $status->value,
            'label' => $stageInfo['label'],
            'progress' => $percent,
            'is_cancelled' => $this->isCancelled($jobId),
            'meta' => $meta,
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::put("docver:pipeline:job_{$jobId}:status", $data, 3600);

        // Update database record
        ComparisonJob::where('id', $jobId)->update([
            'processing_progress' => $percent,
            'current_stage' => $stage,
            'status' => $status,
        ]);
    }

    public function getProgress(int $jobId): array
    {
        $cached = Cache::get("docver:pipeline:job_{$jobId}:status");
        if ($cached) {
            return $cached;
        }

        $job = ComparisonJob::find($jobId);
        if (! $job) {
            return [
                'job_id' => $jobId,
                'stage' => 'unknown',
                'current_stage' => 'unknown',
                'status' => 'failed',
                'label' => 'Job not found',
                'progress' => 0,
                'is_cancelled' => false,
            ];
        }

        return [
            'job_id' => $jobId,
            'stage' => $job->current_stage ?? 'queued',
            'current_stage' => $job->current_stage ?? 'queued',
            'label' => self::STAGES[$job->current_stage]['label'] ?? ($job->current_stage ?? 'Queued'),
            'progress' => $job->processing_progress ?? 0,
            'is_cancelled' => $job->cancelled_at !== null,
            'status' => $job->status->value,
        ];
    }

    public function cancel(int $jobId): void
    {
        Cache::put("docver:pipeline:job_{$jobId}:cancelled", true, 3600);

        ComparisonJob::where('id', $jobId)->update([
            'status' => ComparisonStatus::CANCELLED,
            'cancelled_at' => now(),
            'current_stage' => 'cancelled',
        ]);

        $data = $this->getProgress($jobId);
        $data['is_cancelled'] = true;
        $data['stage'] = 'cancelled';
        $data['label'] = 'Processing cancelled by user';
        Cache::put("docver:pipeline:job_{$jobId}:status", $data, 3600);
    }

    public function isCancelled(int $jobId): bool
    {
        if (Cache::get("docver:pipeline:job_{$jobId}:cancelled", false)) {
            return true;
        }

        return ComparisonJob::where('id', $jobId)
            ->whereNotNull('cancelled_at')
            ->exists();
    }
}
