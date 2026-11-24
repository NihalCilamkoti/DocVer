<?php

namespace App\DTOs;

readonly class CreateComparisonDTO
{
    public function __construct(
        public int $workspaceId,
        public string $title,
        public int $documentAId,
        public int $documentBId,
        public array $options = [],
    ) {}

    public static function fromArray(array $data, int $workspaceId): self
    {
        $options = $data['options'] ?? [];
        if (! empty($data['focus_prompt'])) {
            $options['focus_prompt'] = $data['focus_prompt'];
        } elseif (! empty($data['prompt'])) {
            $options['focus_prompt'] = $data['prompt'];
        }

        return new self(
            workspaceId: $workspaceId,
            title: $data['title'] ?? 'Document Comparison',
            documentAId: (int) $data['document_a_id'],
            documentBId: (int) $data['document_b_id'],
            options: $options,
        );
    }
}
