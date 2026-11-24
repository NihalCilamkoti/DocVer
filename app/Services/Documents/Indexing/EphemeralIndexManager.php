<?php

namespace App\Services\Documents\Indexing;

use App\Services\Documents\DTOs\DocumentChunkDTO;
use Illuminate\Support\Facades\Cache;

class EphemeralIndexManager
{
    /**
     * Default TTL for ephemeral comparison index: 24 hours (86,400 seconds)
     */
    public const DEFAULT_TTL = 86400;

    /**
     * Store chunk array for a specific document slot in Redis / Cache.
     *
     * @param DocumentChunkDTO[] $chunks
     */
    public function storeChunks(int $comparisonJobId, string $slot, array $chunks, int $ttl = self::DEFAULT_TTL): void
    {
        $key = $this->chunksKey($comparisonJobId, $slot);
        $serializable = array_map(fn($c) => $c instanceof DocumentChunkDTO ? $c->toArray() : (array) $c, $chunks);

        Cache::put($key, $serializable, $ttl);

        // Update list of active indexed slots for this job
        $metaKey = $this->metaKey($comparisonJobId);
        $meta = Cache::get($metaKey, ['slots' => [], 'total_chunks' => 0]);
        if (! in_array($slot, $meta['slots'])) {
            $meta['slots'][] = $slot;
        }
        $meta['total_chunks'] += count($chunks);
        $meta['updated_at'] = now()->toIso8601String();

        Cache::put($metaKey, $meta, $ttl);
    }

    /**
     * Retrieve chunks for a specific slot or both slots.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getChunks(int $comparisonJobId, ?string $slot = null): array
    {
        if ($slot !== null) {
            return Cache::get($this->chunksKey($comparisonJobId, $slot), []);
        }

        $all = [];
        foreach (['A', 'B'] as $s) {
            $chunks = Cache::get($this->chunksKey($comparisonJobId, $s), []);
            $all = array_merge($all, $chunks);
        }
        return $all;
    }

    /**
     * Store search index / metadata for the comparison job.
     */
    public function storeIndexMetadata(int $comparisonJobId, array $metadata, int $ttl = self::DEFAULT_TTL): void
    {
        $metaKey = $this->metaKey($comparisonJobId);
        $existing = Cache::get($metaKey, []);
        Cache::put($metaKey, array_merge($existing, $metadata), $ttl);
    }

    /**
     * Check if comparison index exists for this job.
     */
    public function hasIndex(int $comparisonJobId): bool
    {
        return Cache::has($this->metaKey($comparisonJobId));
    }

    /**
     * Clear and delete all ephemeral chunks and indexes for this comparison job.
     */
    public function clearIndex(int $comparisonJobId): void
    {
        Cache::forget($this->chunksKey($comparisonJobId, 'A'));
        Cache::forget($this->chunksKey($comparisonJobId, 'B'));
        Cache::forget($this->metaKey($comparisonJobId));
    }

    protected function chunksKey(int $comparisonJobId, string $slot): string
    {
        return "docver:index:job_{$comparisonJobId}:slot_{$slot}:chunks";
    }

    protected function metaKey(int $comparisonJobId): string
    {
        return "docver:index:job_{$comparisonJobId}:meta";
    }
}
