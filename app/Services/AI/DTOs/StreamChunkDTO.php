<?php

namespace App\Services\AI\DTOs;

readonly class StreamChunkDTO
{
    public function __construct(
        public string $delta,
        public bool $isDone = false,
        public ?UsageStatsDTO $usage = null,
    ) {}
}
