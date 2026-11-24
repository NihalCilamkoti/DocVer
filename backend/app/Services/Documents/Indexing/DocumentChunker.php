<?php

namespace App\Services\Documents\Indexing;

use App\Services\Documents\DTOs\DocumentChunkDTO;
use App\Services\Documents\DTOs\ExtractedDocumentDTO;
use Illuminate\Support\Str;

class DocumentChunker
{
    public function __construct(
        protected int $chunkSize = 600,
        protected int $chunkOverlap = 100
    ) {}

    /**
     * Chunk an extracted document into bounded pieces preserving page boundaries and sections.
     *
     * @return DocumentChunkDTO[]
     */
    public function chunk(ExtractedDocumentDTO $document, int $documentId, string $slot): array
    {
        $chunks = [];
        $chunkIndex = 0;

        foreach ($document->pages as $page) {
            $pageText = trim($page->text);
            $currentSection = ! empty($page->sections) ? $page->sections[0] : null;

            if (empty($pageText)) {
                // If page has tables but no raw text
                if (! empty($page->tables)) {
                    foreach ($page->tables as $tableIdx => $table) {
                        $tableText = $table->toText();
                        $chunkIndex++;
                        $chunks[] = new DocumentChunkDTO(
                            chunkId: "doc_{$documentId}_p{$page->pageNumber}_tbl{$tableIdx}_c{$chunkIndex}",
                            documentSlot: $slot,
                            documentId: $documentId,
                            pageNumber: $page->pageNumber,
                            sectionTitle: $currentSection ?? "Table " . ($tableIdx + 1),
                            content: $tableText,
                            startChar: 0,
                            endChar: strlen($tableText),
                            tokenCount: (int) ceil(str_word_count($tableText) * 1.3),
                            metadata: [
                                'is_table' => true,
                                'table_index' => $tableIdx,
                                'rows' => count($table->rows),
                                'cols' => count($table->headers),
                            ]
                        );
                    }
                }
                continue;
            }

            // Split page text into overlapping windows
            $pageLen = strlen($pageText);
            $start = 0;

            while ($start < $pageLen) {
                $end = min($start + $this->chunkSize, $pageLen);

                // If not at the end of the text, try to break at a sentence or newline boundary
                if ($end < $pageLen) {
                    $boundary = $this->findSentenceBoundary($pageText, $start, $end);
                    if ($boundary > $start + ($this->chunkSize / 2)) {
                        $end = $boundary;
                    }
                }

                $chunkContent = trim(substr($pageText, $start, $end - $start));

                if (! empty($chunkContent)) {
                    $chunkIndex++;
                    $tokenEstimate = (int) ceil(str_word_count($chunkContent) * 1.3);

                    $chunks[] = new DocumentChunkDTO(
                        chunkId: "doc_{$documentId}_p{$page->pageNumber}_c{$chunkIndex}",
                        documentSlot: $slot,
                        documentId: $documentId,
                        pageNumber: $page->pageNumber,
                        sectionTitle: $currentSection,
                        content: $chunkContent,
                        startChar: $start,
                        endChar: $end,
                        tokenCount: $tokenEstimate,
                        metadata: [
                            'is_scanned' => $page->isScanned,
                            'total_pages' => $document->totalPages,
                        ]
                    );
                }

                // Move forward with overlap
                if ($end >= $pageLen) {
                    break;
                }
                $start = $end - $this->chunkOverlap;
                if ($start < 0) {
                    $start = 0;
                }
            }

            // Also chunk any structured tables on this page
            foreach ($page->tables as $tableIdx => $table) {
                $tableText = $table->toText();
                $chunkIndex++;
                $chunks[] = new DocumentChunkDTO(
                    chunkId: "doc_{$documentId}_p{$page->pageNumber}_tbl{$tableIdx}_c{$chunkIndex}",
                    documentSlot: $slot,
                    documentId: $documentId,
                    pageNumber: $page->pageNumber,
                    sectionTitle: $currentSection ?? "Table " . ($tableIdx + 1),
                    content: $tableText,
                    startChar: 0,
                    endChar: strlen($tableText),
                    tokenCount: (int) ceil(str_word_count($tableText) * 1.3),
                    metadata: [
                        'is_table' => true,
                        'table_index' => $tableIdx,
                    ]
                );
            }
        }

        return $chunks;
    }

    protected function findSentenceBoundary(string $text, int $start, int $end): int
    {
        $slice = substr($text, $start, $end - $start);
        $matches = [];
        // Look for newline or period followed by space
        if (preg_match_all('/(\.\s+|\n\n|\n)/', $slice, $matches, PREG_OFFSET_CAPTURE)) {
            $lastMatch = end($matches[0]);
            return $start + $lastMatch[1] + strlen($lastMatch[0]);
        }
        return $end;
    }
}
