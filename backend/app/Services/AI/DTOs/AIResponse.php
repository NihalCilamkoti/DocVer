<?php

namespace App\Services\AI\DTOs;

readonly class AIResponse
{
    public function __construct(
        public string $content,
        public ?array $structuredData = null,
        public string $provider = 'unknown',
        public string $model = 'unknown',
        public ?UsageStatsDTO $usage = null,
        public bool $isFallback = false,
        public array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'structured_data' => $this->structuredData,
            'provider' => $this->provider,
            'model' => $this->model,
            'usage' => $this->usage?->toArray(),
            'is_fallback' => $this->isFallback,
        ];
    }
}
