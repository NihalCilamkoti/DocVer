<?php

namespace App\Services\Documents;

use App\Models\ComparisonJob;
use App\Models\DocumentMetadata;
use App\Models\Workspace;
use App\Services\Documents\Contracts\MalwareScannerInterface;
use App\Services\Documents\Security\FileSignatureValidator;
use App\Services\Documents\Security\ZipBombGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentService
{
    public const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'csv', 'ppt', 'pptx',
        'png', 'jpg', 'jpeg', 'webp', 'tiff', 'tif'
    ];

    public function __construct(
        protected FileSignatureValidator $signatureValidator,
        protected ZipBombGuard $zipBombGuard,
        protected MalwareScannerInterface $malwareScanner
    ) {}

    public function stageDocument(
        Workspace $workspace,
        ?int $comparisonJobId,
        string $slot,
        UploadedFile $file
    ): DocumentMetadata {
        $realPath = $file->getRealPath();
        $claimedExt = strtolower($file->getClientOriginalExtension());

        // 1. Verify file size limit
        $maxBytes = $workspace->max_file_size_mb * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                'file' => ["File exceeds workspace limit of {$workspace->max_file_size_mb} MB."],
            ]);
        }

        // 2. Magic byte file signature validation (never trust extension/MIME header alone)
        $sigResult = $this->signatureValidator->validate($realPath, $claimedExt);
        if (! $sigResult->isValid) {
            throw ValidationException::withMessages([
                'file' => [$sigResult->errorMessage],
            ]);
        }

        // 3. ZIP bomb verification for archive containers (DOCX, XLSX, PPTX)
        if ($this->zipBombGuard->isZipArchive($realPath)) {
            try {
                $this->zipBombGuard->assertSafe($realPath);
            } catch (\Throwable $e) {
                throw ValidationException::withMessages([
                    'file' => [$e->getMessage()],
                ]);
            }
        }

        // 4. Malware scan
        $this->malwareScanner->scan($realPath);

        // 5. Compute SHA-256 integrity checksum & check for duplicate in same comparison job
        $hash = hash_file('sha256', $realPath);

        if ($comparisonJobId) {
            $duplicate = DocumentMetadata::where('comparison_job_id', $comparisonJobId)
                ->where('checksum_sha256', $hash)
                ->whereNull('deleted_at')
                ->first();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'file' => ["Duplicate document detected. This identical file is already uploaded as Document {$duplicate->slot}."],
                ]);
            }
        }

        // 6. Private temporary storage
        $folder = $comparisonJobId ? "{$workspace->id}/{$comparisonJobId}" : "{$workspace->id}/staging";
        $ext = $sigResult->detectedFormat ?? $claimedExt;
        $storedPath = $file->storeAs(
            "documents/{$folder}",
            Str::uuid() . ".{$ext}",
            'local'
        );

        $retentionDays = $workspace->retention_days ?: 30;

        $pageCount = null;
        $fullText = '';
        try {
            $fullDiskPath = Storage::disk('local')->path($storedPath);
            $parser = app(\App\Services\Documents\Parsers\DocumentParserFactory::class)->getParser($ext);
            $dto = $parser->parse($fullDiskPath);
            $pageCount = $dto->totalPages ?: 1;
            $fullText = method_exists($dto, 'getFullText') ? $dto->getFullText() : ($dto->fullText ?? '');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info("Pre-extraction during stage: " . $e->getMessage());
        }

        $doc = DocumentMetadata::create([
            'workspace_id' => $workspace->id,
            'comparison_job_id' => $comparisonJobId,
            'slot' => $slot,
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $storedPath,
            'mime_type' => $sigResult->mimeType ?? $file->getMimeType(),
            'file_size' => $file->getSize(),
            'page_count' => $pageCount,
            'checksum_sha256' => $hash,
            'is_sanitized' => true,
            'expires_at' => now()->addDays($retentionDays),
        ]);

        if (!empty(trim($fullText))) {
            \Illuminate\Support\Facades\Cache::put("doc_content_{$doc->id}", $fullText, 3600);
        }

        return $doc;
    }

    public function purgeExpiredDocuments(): int
    {
        $expired = DocumentMetadata::where('expires_at', '<=', now())
            ->whereNull('deleted_at')
            ->get();

        $count = 0;
        foreach ($expired as $doc) {
            if (Storage::disk('local')->exists($doc->storage_path)) {
                Storage::disk('local')->delete($doc->storage_path);
            }
            $doc->update(['deleted_at' => now()]);
            $count++;
        }

        return $count;
    }
}
