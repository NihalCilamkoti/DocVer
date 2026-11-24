<?php

namespace App\Services\Documents\DTOs;

readonly class ExtractedTableDTO
{
    /**
     * @param string[] $headers
     * @param array<int, array<int, string>> $rows
     */
    public function __construct(
        public array $headers,
        public array $rows,
        public ?string $title = null,
        public int $pageNumber = 1,
    ) {}

    public function toText(): string
    {
        $lines = [];
        if ($this->title) {
            $lines[] = "[Table: {$this->title}]";
        }
        if (! empty($this->headers)) {
            $lines[] = implode(' | ', $this->headers);
            $lines[] = str_repeat('-', 30);
        }
        foreach ($this->rows as $row) {
            $lines[] = implode(' | ', array_map('strval', $row));
        }
        return implode("\n", $lines);
    }
}
