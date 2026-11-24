<?php

namespace App\Services\Comparison\DTOs;

readonly class SectionMatchDTO
{
    public function __construct(
        public AnalyzedSectionDTO $sectionA,
        public AnalyzedSectionDTO $sectionB,
        public float $similarityScore, // 0.0 to 1.0
        public string $matchType = 'semantic', // 'exact_heading', 'clause_number', 'semantic'
    ) {}
}
