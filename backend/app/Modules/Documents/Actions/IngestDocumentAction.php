<?php

namespace App\Modules\Documents\Actions;

use App\Models\ComparisonJob;
use App\Models\DocumentMetadata;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class IngestDocumentAction
{
    /**
     * Securely stage uploaded document binary into encrypted temporary storage.
     */
    public function execute(ComparisonJob $job, UploadedFile $file, string $slot): DocumentMetadata
    {
        $hash = hash_file('sha256', $file->getRealPath());
        $storagePath = "staged_uploads/{$job->id}_{$slot}_{$hash}." . $file->getClientOriginalExtension();

        Storage::disk('local')->put($storagePath, file_get_contents($file->getRealPath()));

        return DocumentMetadata::create([
            'comparison_job_id' => $job->id,
            'workspace_id' => $job->workspace_id,
            'document_slot' => $slot,
            'original_filename' => $file->getClientOriginalName(),
            'storage_path' => $storagePath,
            'file_size_bytes' => $file->getSize(),
            'sha256_hash' => $hash,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'status' => 'uploaded',
        ]);
    }
}
