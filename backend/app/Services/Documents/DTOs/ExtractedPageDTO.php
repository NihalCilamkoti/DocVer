<?php

namespace App\Services\Documents\DTOs;

readonly class ExtractedPageDTO
{
    /**
     * @param ExtractedTableDTO[] $tables
     * @param string[] $sections
     */
    public function __construct(
        public int $pageNumber,
        public string $text,
        public array $tables = [],
        public array $sections = [],
        public bool $isScanned = false,
        public int $wordCount = 0,
    ) {}
}
