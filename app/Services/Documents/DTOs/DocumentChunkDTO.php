<?php

namespace App\Services\Documents\DTOs;

readonly class DocumentChunkDTO
{
    public function __construct(
        public string $chunkId,
        public string $documentSlot, // 'A' or 'B'
        public int $documentId,
        public int $pageNumber,
        public ?string $sectionTitle,
        public string $content,
        public int $startChar,
        public int $endChar,
        public int $tokenCount = 0,
        public array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'chunk_id' => $this->chunkId,
            'slot' => $this->documentSlot,
            'document_id' => $this->documentId,
            'page_number' => $this->pageNumber,
            'section_title' => $this->sectionTitle,
            'content' => $this->content,
            'start_char' => $this->startChar,
            'end_char' => $this->endChar,
            'token_count' => $this->tokenCount,
            'metadata' => $this->metadata,
        ];
    }
}
