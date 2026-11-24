<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\UploadDocumentRequest;
use App\Http\Resources\DocumentMetadataResource;
use App\Models\DocumentMetadata;
use App\Models\Workspace;
use App\Services\Documents\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DocumentController extends BaseApiController
{
    public function __construct(
        protected DocumentService $documentService
    ) {}

    /**
     * Workspace-scoped document upload
     */
    public function upload(UploadDocumentRequest $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('view', $workspace);

        $comparisonJobId = $request->filled('comparison_job_id')
            ? (int) $request->input('comparison_job_id')
            : null;

        $doc = $this->documentService->stageDocument(
            workspace: $workspace,
            comparisonJobId: $comparisonJobId,
            slot: $request->validated('slot'),
            file: $request->file('file')
        );

        return $this->successResponse(
            new DocumentMetadataResource($doc),
            'Document uploaded and verified',
            201
        );
    }

    /**
     * Global direct upload endpoint: POST /api/v1/upload
     */
    public function directUpload(UploadDocumentRequest $request): JsonResponse
    {
        $user = $request->user();

        // Resolve target workspace: either from request, or user's owned/member workspace
        $workspaceId = $request->input('workspace_id');
        $workspace = $workspaceId
            ? $user->workspaces()->where('workspaces.id', $workspaceId)->first()
            : $user->workspaces()->first();

        if (! $workspace) {
            return $this->errorResponse('No accessible workspace found for upload', [], 403);
        }

        Gate::authorize('view', $workspace);

        $comparisonJobId = $request->filled('comparison_job_id')
            ? (int) $request->input('comparison_job_id')
            : null;

        $doc = $this->documentService->stageDocument(
            workspace: $workspace,
            comparisonJobId: $comparisonJobId,
            slot: $request->validated('slot'),
            file: $request->file('file')
        );

        return $this->successResponse(
            new DocumentMetadataResource($doc),
            'Document uploaded and verified',
            201
        );
    }

    public function show(Workspace $workspace, DocumentMetadata $document): JsonResponse
    {
        Gate::authorize('view', $workspace);

        if ($document->workspace_id !== $workspace->id) {
            return $this->errorResponse('Document not found in this workspace', [], 404);
        }

        return $this->successResponse(new DocumentMetadataResource($document));
    }

    /**
     * Retrieve extracted text and paginated pages for Document Preview & Editing: GET /api/v1/documents/{document}/content
     */
    public function content(DocumentMetadata $document): JsonResponse
    {
        if ($document->workspace && ! Gate::allows('view', $document->workspace)) {
            return $this->errorResponse('Unauthorized to access this document.', [], 403);
        }

        $docText = $this->extractDocumentText($document);

        $rawPages = preg_split('/\f|(?:\r?\n){4,}/', $docText);
        $pages = [];
        if ($rawPages && count($rawPages) > 1) {
            foreach ($rawPages as $idx => $pText) {
                if (trim($pText) !== '') {
                    $pages[] = [
                        'page' => count($pages) + 1,
                        'content' => trim($pText),
                    ];
                }
            }
        }

        if (empty($pages)) {
            $trimmed = trim($docText);
            if ($trimmed !== '') {
                $chunks = str_split($trimmed, 2400);
                foreach ($chunks as $idx => $chunk) {
                    $pages[] = [
                        'page' => $idx + 1,
                        'content' => $chunk,
                    ];
                }
            } else {
                $pages[] = [
                    'page' => 1,
                    'content' => "Document metadata verified for {$document->original_name} ({$document->mime_type}).",
                ];
            }
        }

        return $this->successResponse([
            'id' => $document->id,
            'name' => $document->original_name,
            'mime_type' => $document->mime_type,
            'size' => $document->file_size,
            'page_count' => $document->page_count ?: count($pages),
            'checksum' => $document->checksum_sha256,
            'text' => $docText,
            'pages' => $pages,
        ]);
    }

    /**
     * Direct single-document AI query endpoint: POST /api/v1/documents/query
     */
    public function directQueryDocument(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_id' => 'required',
            'prompt' => 'sometimes|nullable|string|max:4000',
            'message' => 'sometimes|nullable|string|max:4000',
        ]);

        $document = DocumentMetadata::findOrFail($validated['document_id']);
        return $this->queryDocument($request, $document);
    }

    /**
     * Single-document AI query and summarization endpoint: POST /api/v1/documents/{document}/query
     */
    public function queryDocument(\Illuminate\Http\Request $request, DocumentMetadata $document): JsonResponse
    {
        if ($document->workspace && ! Gate::allows('view', $document->workspace)) {
            return $this->errorResponse('Unauthorized to access this document.', [], 403);
        }

        $validated = $request->validate([
            'prompt' => 'sometimes|nullable|string|max:4000',
            'message' => 'sometimes|nullable|string|max:4000',
        ]);

        $query = $validated['prompt'] ?? $validated['message'] ?? 'Give me an executive summary and list of all items and clauses.';

        $orchestrator = app(\App\Services\AI\AIOrchestrator::class);
        $result = $orchestrator->answerDocumentQuery(
            document: $document,
            query: $query,
            userId: $request->user()?->id
        );

        return $this->successResponse($result);
    }

    protected function extractDocumentText(DocumentMetadata $document): string
    {
        $cacheKey = "docver:doc_text:{$document->id}";
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        if ($document->comparison_job_id) {
            $chunks = app(\App\Services\Documents\Indexing\EphemeralIndexManager::class)->getChunks(
                $document->comparison_job_id,
                $document->slot
            );
            if (! empty($chunks)) {
                $text = implode("\n\n", array_column($chunks, 'content'));
                \Illuminate\Support\Facades\Cache::put($cacheKey, $text, 3600);
                return $text;
            }
        }

        $candidates = [
            \Illuminate\Support\Facades\Storage::disk('local')->path($document->storage_path),
            storage_path('app/' . $document->storage_path),
            storage_path('app/private/' . $document->storage_path),
        ];

        $targetPath = null;
        foreach ($candidates as $p) {
            if (file_exists($p)) {
                $targetPath = $p;
                break;
            }
        }

        if ($targetPath) {
            $ext = strtolower(pathinfo($document->original_name, PATHINFO_EXTENSION));
            try {
                $parser = app(\App\Services\Documents\Parsers\DocumentParserFactory::class)->getParser($ext);
                $dto = $parser->parse($targetPath);
                $fullText = method_exists($dto, 'getFullText') ? $dto->getFullText() : ($dto->fullText ?? '');
                if (! empty(trim($fullText))) {
                    $clean = mb_convert_encoding($fullText, 'UTF-8', 'UTF-8');
                    \Illuminate\Support\Facades\Cache::put($cacheKey, $clean, 3600);

                    // Update page count if not set
                    if (empty($document->page_count) && !empty($dto->totalPages)) {
                        $document->update(['page_count' => $dto->totalPages]);
                    }

                    return $clean;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Parser failed for {$document->original_name}: " . $e->getMessage());
            }

            // Only attempt direct string reading for plain text or delimited formats
            if (in_array($ext, ['txt', 'csv', 'json', 'md', 'html', 'rtf'])) {
                $raw = @file_get_contents($targetPath);
                if ($raw !== false && $raw !== '') {
                    $clean = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
                    \Illuminate\Support\Facades\Cache::put($cacheKey, $clean, 3600);
                    return $clean;
                }
            }
        }

        return "Document text extraction is pending or verified for {$document->original_name}.";
    }
}
