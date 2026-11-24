<?php

namespace App\Services\Comparison\DTOs;

readonly class AnalyzedSectionDTO
{
    public function __construct(
        public string $id,
        public string $slot, // 'A' or 'B'
        public string $title,
        public ?string $clauseNumber = null,
        public int $pageStart = 1,
        public int $pageEnd = 1,
        public string $content = '',
        public string $category = 'General',
        public array $chunks = [],
    ) {}

    public function getWordCount(): int
    {
        return str_word_count($this->content);
    }
}
