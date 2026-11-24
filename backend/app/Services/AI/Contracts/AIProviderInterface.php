<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;
use Generator;

interface AIProviderInterface
{
    /**
     * Unique identifier for provider (e.g. 'gemini', 'openrouter').
     */
    public function getName(): string;

    /**
     * Whether valid API credentials are configured in environment.
     */
    public function isConfigured(): bool;

    /**
     * Execute completion request (text or structured JSON).
     */
    public function generate(AIRequest $request): AIResponse;

    /**
     * Execute streaming completion, yielding StreamChunkDTO.
     *
     * @return Generator<int, \App\Services\AI\DTOs\StreamChunkDTO, mixed, void>
     */
    public function stream(AIRequest $request): Generator;

    /**
     * Whether this provider natively supports schema-guided structured output.
     */
    public function supportsStructuredOutput(): bool;

    /**
     * Whether this provider supports token-by-token streaming.
     */
    public function supportsStreaming(): bool;

    /**
     * Maximum token window supported.
     */
    public function getMaxContextLength(): int;

    /**
     * Calculate estimated USD cost for token consumption.
     */
    public function calculateCost(string $model, int $inputTokens, int $outputTokens): float;

    /**
     * Default model for given task type ('general', 'comparison', 'chat').
     */
    public function getDefaultModel(string $taskType = 'general'): string;

    /**
     * Lightweight health check to test provider availability and latency.
     */
    public function ping(): bool;

    /**
     * Check if the provider supports a specific task type.
     */
    public function supportsTask(string $taskType, ?string $model = null): bool;

    /**
     * Extract quota and rate-limit headers from HTTP response.
     *
     * @param array<string, mixed> $headers
     * @return array<string, mixed>
     */
    public function extractQuotaHeaders(array $headers): array;
}
