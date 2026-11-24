<?php

namespace App\Services\AI;

interface AIServiceInterface
{
    /**
     * Stub interface for AI parsing and comparison engine.
     * Full implementation reserved for Phase 4+.
     */
    public function compare(string $docAPath, string $docBPath, array $options = []): array;

    public function generateAnswer(string $comparisonJobId, string $query, array $context = []): array;
}
