<?php

namespace App\Services\AI\Context;

use App\Models\ComparisonJob;
use App\Models\DocumentMetadata;
use App\Services\AI\Intent\TaskIntentDTO;
use App\Services\AI\Security\PromptSanitizer;
use App\Services\Documents\Indexing\EphemeralIndexManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ContextBuilder
{
    public function __construct(
        protected EphemeralIndexManager $indexManager,
        protected PromptSanitizer $sanitizer
    ) {}

    /**
     * Build rich, section-aware context for a ComparisonJob based on user task intent.
     */
    public function buildComparisonContext(ComparisonJob $job, TaskIntentDTO $intent): ContextResultDTO
    {
        $jobId = $job->id;
        $chunksA = $this->indexManager->getChunks($jobId, 'A');
        $chunksB = $this->indexManager->getChunks($jobId, 'B');

        // Fallback: If cache expired or not in Redis, rebuild lightweight chunks from documents
        if (empty($chunksA) && $job->documentA) {
            $chunksA = $this->rebuildChunksFromDocument($job->documentA, 'A');
        }
        if (empty($chunksB) && $job->documentB) {
            $chunksB = $this->rebuildChunksFromDocument($job->documentB, 'B');
        }

        // STRICT DOCUMENT ISOLATION:
        // When a single document slot is targeted, completely eliminate the other document's chunks
        if ($intent->targetSlot === 'A') {
            $chunksB = [];
        } elseif ($intent->targetSlot === 'B') {
            $chunksA = [];
        }

        return $this->assembleContext(
            chunksA: $chunksA,
            chunksB: $chunksB,
            intent: $intent,
            docAName: $job->documentA?->original_name ?? 'Document A',
            docBName: $job->documentB?->original_name ?? 'Document B'
        );
    }

    /**
     * Build rich, section-aware context for a single DocumentMetadata.
     */
    public function buildSingleDocumentContext(DocumentMetadata $document, TaskIntentDTO $intent): ContextResultDTO
    {
        $slot = $document->slot ?: ($intent->targetSlot === 'B' ? 'B' : 'A');
        $chunks = [];

        if ($document->comparison_job_id) {
            $chunks = $this->indexManager->getChunks($document->comparison_job_id, $slot);
        }

        if (empty($chunks)) {
            $chunks = $this->rebuildChunksFromDocument($document, $slot);
        }

        return $this->assembleContext(
            chunksA: $slot === 'B' ? [] : $chunks,
            chunksB: $slot === 'B' ? $chunks : [],
            intent: $intent,
            docAName: $slot === 'B' ? '' : $document->original_name,
            docBName: $slot === 'B' ? $document->original_name : ''
        );
    }

    /**
     * Core context assembly algorithm.
     *
     * @param array<int, array<string, mixed>> $chunksA
     * @param array<int, array<string, mixed>> $chunksB
     */
    protected function assembleContext(
        array $chunksA,
        array $chunksB,
        TaskIntentDTO $intent,
        string $docAName,
        string $docBName
    ): ContextResultDTO {
        $contextBlocks = [];
        $citations = [];
        $availableSections = [];
        $targetFound = false;
        $specificTargetMatch = null;

        // 1. Gather all section titles and check availability
        foreach (array_merge($chunksA, $chunksB) as $c) {
            $title = $c['section_title'] ?? null;
            if ($title && !in_array($title, $availableSections)) {
                $availableSections[] = $title;
            }
        }

        // 2. Section-Specific Intent (e.g. "Summarize only section 4", "Check page 2")
        if ($intent->isSectionSpecific() && !empty($intent->targetSections)) {
            $selectedA = $this->findSectionChunks($chunksA, $intent->targetSections);
            $selectedB = $this->findSectionChunks($chunksB, $intent->targetSections);

            if (!empty($selectedA) || !empty($selectedB)) {
                $targetFound = true;
                $specificTargetMatch = implode(', ', $intent->targetSections);
            }

            // Include surrounding context for continuity if found
            $chunksToIncludeA = !empty($selectedA) ? $selectedA : (empty($chunksB) ? $chunksA : array_slice($chunksA, 0, 2));
            $chunksToIncludeB = !empty($selectedB) ? $selectedB : (empty($chunksA) ? $chunksB : array_slice($chunksB, 0, 2));

            if ($intent->targetSlot !== 'B' && !empty($chunksToIncludeA)) {
                $this->appendChunksToBlocks($contextBlocks, $citations, $chunksToIncludeA, 'A', $docAName);
            }
            if ($intent->targetSlot !== 'A' && !empty($chunksToIncludeB)) {
                $this->appendChunksToBlocks($contextBlocks, $citations, $chunksToIncludeB, 'B', $docBName);
            }

            $formatted = implode("\n\n", $contextBlocks);
            return new ContextResultDTO(
                contextBlocks: $contextBlocks,
                citations: $citations,
                targetFound: $targetFound,
                formattedContext: $formatted,
                availableSections: $availableSections,
                specificTargetMatch: $specificTargetMatch,
                completenessScore: $targetFound ? 1.0 : 0.2,
                slotScope: $intent->targetSlot
            );
        }

        // 3. Clause Diff / Numerical / Targeted Intent (e.g. "payment terms", "termination", "roles")
        if (!empty($intent->targetClauses) || $intent->isClauseDiff() || $intent->isNumerical()) {
            $matchedA = $this->findClauseChunks($chunksA, array_merge($intent->targetClauses, $intent->keywords));
            $matchedB = $this->findClauseChunks($chunksB, array_merge($intent->targetClauses, $intent->keywords));

            if (!empty($matchedA) || !empty($matchedB)) {
                $targetFound = true;
                $specificTargetMatch = !empty($intent->targetClauses) ? implode(', ', $intent->targetClauses) : implode(', ', array_slice($intent->keywords, 0, 3));
                if ($intent->targetSlot !== 'B') {
                    $chunksToAddA = !empty($matchedA) ? $matchedA : array_slice($chunksA, 0, 3);
                    if (!empty($chunksToAddA)) {
                        $this->appendChunksToBlocks($contextBlocks, $citations, $chunksToAddA, 'A', $docAName);
                    }
                }
                if ($intent->targetSlot !== 'A') {
                    $chunksToAddB = !empty($matchedB) ? $matchedB : array_slice($chunksB, 0, 3);
                    if (!empty($chunksToAddB)) {
                        $this->appendChunksToBlocks($contextBlocks, $citations, $chunksToAddB, 'B', $docBName);
                    }
                }
            } else {
                // Topic NOT found in either document: supply representative outline so AI can explain what IS in the docs
                $targetFound = false;
                if ($intent->targetSlot !== 'B' && !empty($chunksA)) {
                    $this->appendChunksToBlocks($contextBlocks, $citations, array_slice($chunksA, 0, 3), 'A', $docAName);
                }
                if ($intent->targetSlot !== 'A' && !empty($chunksB)) {
                    $this->appendChunksToBlocks($contextBlocks, $citations, array_slice($chunksB, 0, 3), 'B', $docBName);
                }
            }

            $formatted = implode("\n\n", $contextBlocks);
            return new ContextResultDTO(
                contextBlocks: $contextBlocks,
                citations: $citations,
                targetFound: $targetFound,
                formattedContext: $formatted,
                availableSections: $availableSections,
                specificTargetMatch: $specificTargetMatch,
                completenessScore: $targetFound ? 1.0 : 0.1,
                slotScope: $intent->targetSlot
            );
        }

        // 4. Summarization / Whole Document / General QA
        // Retrieve distributed chunks across beginning, middle, and end of the documents
        if ($intent->targetSlot !== 'B' && !empty($chunksA)) {
            $sampledA = $this->sampleDocumentChunks($chunksA, 5);
            $this->appendChunksToBlocks($contextBlocks, $citations, $sampledA, 'A', $docAName);
        }
        if ($intent->targetSlot !== 'A' && !empty($chunksB)) {
            $sampledB = $this->sampleDocumentChunks($chunksB, 5);
            $this->appendChunksToBlocks($contextBlocks, $citations, $sampledB, 'B', $docBName);
        }

        $targetFound = !empty($contextBlocks);
        $formatted = implode("\n\n", $contextBlocks);

        return new ContextResultDTO(
            contextBlocks: $contextBlocks,
            citations: $citations,
            targetFound: $targetFound,
            formattedContext: $formatted,
            availableSections: $availableSections,
            specificTargetMatch: 'whole_document',
            completenessScore: 0.95,
            slotScope: $intent->targetSlot
        );
    }

    /**
     * Find chunks matching specific section numbers or page numbers.
     */
    protected function findSectionChunks(array $chunks, array $targets): array
    {
        $matches = [];
        foreach ($chunks as $chunk) {
            $content = strtolower($chunk['content'] ?? '');
            $title = strtolower($chunk['section_title'] ?? '');
            $page = (string) ($chunk['page_number'] ?? '');

            foreach ($targets as $target) {
                $t = strtolower(trim($target));
                if (empty($t)) continue;

                // Match exact section header, e.g. "section 4", "clause 4", or page number "page 2"
                if (str_contains($title, $t) ||
                    preg_match('/\b(?:section|sec\.?|clause|article)\s*' . preg_quote($t, '/') . '\b/i', $content) ||
                    ($t === "page {$page}" || $t === "p. {$page}" || $t === $page)) {
                    $matches[] = $chunk;
                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * Find chunks matching clause concepts or keywords.
     */
    protected function findClauseChunks(array $chunks, array $keywords): array
    {
        $scored = [];
        $cleanKeywords = array_filter(array_map('strtolower', $keywords), fn($k) => strlen($k) > 2);

        foreach ($chunks as $chunk) {
            $content = strtolower($chunk['content'] ?? '');
            $title = strtolower($chunk['section_title'] ?? '');
            $hits = 0;

            foreach ($cleanKeywords as $kw) {
                if (str_contains($title, $kw)) {
                    $hits += 3; // Section title hit is high priority
                } elseif (str_contains($content, $kw)) {
                    $hits += 1;
                }
            }

            if ($hits > 0) {
                $scored[] = ['chunk' => $chunk, 'hits' => $hits];
            }
        }

        usort($scored, fn($a, $b) => $b['hits'] <=> $a['hits']);
        return array_column(array_slice($scored, 0, 4), 'chunk');
    }

    /**
     * Sample chunks across beginning, middle, and end for representative document coverage.
     */
    protected function sampleDocumentChunks(array $chunks, int $limit = 5): array
    {
        $total = count($chunks);
        if ($total <= $limit) {
            return $chunks;
        }

        // Always include first chunk, middle chunks, and final chunk
        $indexes = [0];
        $step = max(1, (int) floor($total / $limit));
        for ($i = 1; $i < $limit - 1; $i++) {
            $idx = min($total - 2, $i * $step);
            if (!in_array($idx, $indexes)) {
                $indexes[] = $idx;
            }
        }
        $indexes[] = $total - 1;
        $indexes = array_unique($indexes);
        sort($indexes);

        $sampled = [];
        foreach ($indexes as $idx) {
            if (isset($chunks[$idx])) {
                $sampled[] = $chunks[$idx];
            }
        }

        return $sampled;
    }

    /**
     * Format and append chunks to contextBlocks and citations.
     */
    protected function appendChunksToBlocks(
        array &$contextBlocks,
        array &$citations,
        array $chunks,
        string $slot,
        string $docName
    ): void {
        foreach ($chunks as $chunk) {
            $page = $chunk['page_number'] ?? 1;
            $title = $chunk['section_title'] ?? null;
            $content = trim($chunk['content'] ?? '');
            if (empty($content)) continue;

            $contextBlocks[] = $this->sanitizer->wrapDocumentContext(
                slot: $slot,
                pageNumber: $page,
                content: $content,
                sectionTitle: $title,
                documentName: $docName
            );

            // Ground-truth citation excerpt
            $words = preg_split('/\s+/', $content, -1, PREG_SPLIT_NO_EMPTY);
            $quote = implode(' ', array_slice($words, 0, 20)) . (count($words) > 20 ? ' ...' : '');

            $citations[] = [
                'docLabel' => $docName ? "{$docName} (Doc {$slot})" : "Document {$slot}",
                'page' => $page,
                'quote' => $quote,
                'section' => $title,
            ];
        }
    }

    /**
     * Rebuild lightweight chunks from physical document metadata if index is missing.
     */
    protected function rebuildChunksFromDocument(DocumentMetadata $document, string $slot): array
    {
        $chunks = [];
        $candidates = [
            Storage::disk('local')->path($document->storage_path),
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

        if (!$targetPath) {
            return [];
        }

        $ext = strtolower(pathinfo($document->original_name, PATHINFO_EXTENSION));
        if (empty($ext)) {
            $ext = strtolower(pathinfo($document->storage_path, PATHINFO_EXTENSION));
        }

        try {
            $parserFactory = app(\App\Services\Documents\Parsers\DocumentParserFactory::class);
            $parser = $parserFactory->getParser($ext);
            $dto = $parser->parse($targetPath);

            $chunkIdx = 1;
            foreach ($dto->pages as $page) {
                $paragraphs = preg_split('/\n\s*\n/', $page->text, -1, PREG_SPLIT_NO_EMPTY);
                if (empty($paragraphs)) {
                    $paragraphs = [$page->text];
                }

                foreach ($paragraphs as $p) {
                    $clean = trim($p);
                    if (strlen($clean) < 15) continue;

                    $firstLine = strtok($clean, "\n");
                    $title = !empty($page->sections) ? $page->sections[0] : ((strlen($firstLine) < 60 && preg_match('/^[A-Z0-9\s.:\-]+$/', $firstLine)) ? $firstLine : "Section {$chunkIdx}");

                    $chunks[] = [
                        'id' => "rebuilt_{$document->id}_{$chunkIdx}",
                        'slot' => $slot,
                        'page_number' => $page->pageNumber,
                        'section_title' => $title,
                        'content' => $clean,
                    ];
                    $chunkIdx++;
                }
            }

            if (!empty($chunks)) {
                return $chunks;
            }
        } catch (\Throwable $e) {
            Log::warning("ContextBuilder parser error for {$document->original_name}: " . $e->getMessage());
        }

        // Plain text fallback
        $raw = @file_get_contents($targetPath);
        if ($raw && !preg_match('/[\x00-\x08\x0E-\x1F]/', substr($raw, 0, 500))) {
            $paragraphs = preg_split('/\n\s*\n/', $raw, -1, PREG_SPLIT_NO_EMPTY);
            $chunkIdx = 1;
            foreach ($paragraphs as $p) {
                $clean = trim($p);
                if (strlen($clean) < 15) continue;

                $firstLine = strtok($clean, "\n");
                $title = (strlen($firstLine) < 60 && preg_match('/^[A-Z0-9\s.:\-]+$/', $firstLine)) ? $firstLine : "Section {$chunkIdx}";

                $chunks[] = [
                    'id' => "rebuilt_{$document->id}_{$chunkIdx}",
                    'slot' => $slot,
                    'page_number' => (int) ceil($chunkIdx / 3),
                    'section_title' => $title,
                    'content' => $clean,
                ];
                $chunkIdx++;
            }
        }

        return $chunks;
    }
}
