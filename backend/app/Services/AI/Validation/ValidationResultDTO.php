<?php

namespace App\Services\AI\Validation;

class ValidationResultDTO
{
    /**
     * @param bool $isValid
     * @param string $validatedContent
     * @param array<array<string, mixed>> $validatedCitations
     * @param float $relevanceScore (0.0 to 1.0)
     * @param bool $isGrounded
     * @param array<string> $warnings
     */
    public function __construct(
        public bool $isValid,
        public string $validatedContent,
        public array $validatedCitations = [],
        public float $relevanceScore = 1.0,
        public bool $isGrounded = true,
        public array $warnings = []
    ) {}
}
