<?php

namespace App\Services\Comparison\DTOs;

class DocumentRelationshipDTO
{
    /**
     * @param string[] $similarities
     * @param string[] $differences
     * @param string[] $uniqueA
     * @param string[] $uniqueB
     */
    public function __construct(
        public string $purposeA,
        public string $purposeB,
        public string $typeA,
        public string $typeB,
        public string $relationship, // 'versions_of_same_document', 'related_topics', 'different_purposes', 'unrelated'
        public bool $isVersionRevision,
        public string $recommendedMode, // 'general_comparison', 'version_comparison', 'specialized_comparison'
        public string $relationshipSummary,
        public array $similarities = [],
        public array $differences = [],
        public array $uniqueA = [],
        public array $uniqueB = [],
        public float $contentOverlap = 0.0,
        public float $topicSimilarity = 0.0,
        public float $semanticSimilarity = 0.0,
        public float $structuralSimilarity = 0.0,
        public float $textualOverlap = 0.0,
        public string $similarityExplanation = ''
    ) {}

    public function isVersionRevision(): bool
    {
        return $this->isVersionRevision && $this->relationship === 'versions_of_same_document';
    }

    public function isDifferentPurposes(): bool
    {
        return in_array($this->relationship, ['different_purposes', 'unrelated']);
    }
}
