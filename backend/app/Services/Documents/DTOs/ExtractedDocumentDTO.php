<?php

namespace App\Services\Documents\DTOs;

class ExtractedDocumentDTO
{
    /**
     * @param ExtractedPageDTO[] $pages
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $originalName,
        public string $format,
        public int $totalPages,
        public array $pages,
        public array $metadata = [],
        public int $totalWordCount = 0,
    ) {}

    public function getFullText(): string
    {
        $buffer = [];
        foreach ($this->pages as $page) {
            $buffer[] = "--- Page {$page->pageNumber} ---";
            $buffer[] = $page->text;
            foreach ($page->tables as $table) {
                $buffer[] = $table->toText();
            }
        }
        return implode("\n\n", $buffer);
    }

    public function __get(string $name)
    {
        if ($name === 'fullText') {
            return $this->getFullText();
        }
        return null;
    }

    public function __isset(string $name): bool
    {
        return $name === 'fullText' || isset($this->{$name});
    }
}
