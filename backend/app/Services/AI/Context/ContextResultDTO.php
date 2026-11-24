<?php

namespace App\Services\AI\Context;

class ContextResultDTO
{
    /**
     * @param array<string> $contextBlocks Formatted context blocks with slot, page, section header
     * @param array<array<string, mixed>> $citations Ground-truth citations
     * @param bool $targetFound Whether the user's target section/clause was actually located in the document
     * @param string $formattedContext Complete assembled context string
     * @param array<string> $availableSections List of sections/clauses found in the document
     * @param ?string $specificTargetMatch Name of the matched target section or clause
     * @param float $completenessScore 0.0 - 1.0
     * @param string $slotScope 'A', 'B', or 'both'
     * @param array<string, mixed> $metadata Additional context metadata
     */
    public function __construct(
        public array $contextBlocks = [],
        public array $citations = [],
        public bool $targetFound = true,
        public string $formattedContext = '',
        public array $availableSections = [],
        public ?string $specificTargetMatch = null,
        public float $completenessScore = 1.0,
        public string $slotScope = 'both',
        public array $metadata = []
    ) {}
}
