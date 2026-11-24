<?php

namespace App\Services\Comparison\DTOs;

readonly class ComparisonResultDTO
{
    /**
     * @param CandidateFindingDTO[] $findings
     */
    public function __construct(
        public int $similarityScore,
        public string $executiveSummary,
        public array $summary,
        public array $findings,
    ) {}

    public function toArray(): array
    {
        return [
            'similarity_score' => $this->similarityScore,
            'executive_summary' => $this->executiveSummary,
            'summary' => $this->summary,
            'findings' => array_map(fn(CandidateFindingDTO $f) => $f->toArray(), $this->findings),
        ];
    }
}
