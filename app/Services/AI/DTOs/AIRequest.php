<?php

namespace App\Services\AI\DTOs;

readonly class AIRequest
{
    public function __construct(
        public string $prompt,
        public ?string $systemInstruction = null,
        public ?string $model = null,
        public float $temperature = 0.2,
        public ?int $maxTokens = null,
        public ?array $jsonSchema = null,
        public bool $stream = false,
        public string $taskType = 'general', // 'general', 'comparison', 'chat'
        public array $metadata = [],
        public ?int $userId = null,
        public ?int $workspaceId = null,
    ) {}

    public function wantsJson(): bool
    {
        return $this->jsonSchema !== null;
    }

    public function withModel(string $model): self
    {
        return new self(
            prompt: $this->prompt,
            systemInstruction: $this->systemInstruction,
            model: $model,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            jsonSchema: $this->jsonSchema,
            stream: $this->stream,
            taskType: $this->taskType,
            metadata: $this->metadata,
            userId: $this->userId,
            workspaceId: $this->workspaceId,
        );
    }
}
