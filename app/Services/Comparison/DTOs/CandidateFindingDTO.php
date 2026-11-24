<?php

namespace App\Services\Comparison\DTOs;

readonly class CandidateFindingDTO
{
    public function __construct(
        public string $id,
        public string $type, // 'modified', 'missing', 'added', 'numerical', 'financial', 'contradiction', 'matching'
        public string $category,
        public string $severity, // 'high', 'medium', 'low', 'none'
        public string $title,
        public ?int $docAPage = null,
        public ?string $docAEvidence = null,
        public ?int $docBPage = null,
        public ?string $docBEvidence = null,
        public string $explanation = '',
        public float $confidence = 0.95,
        public ?array $metrics = null,
        public string $verificationStatus = 'candidate', // 'candidate', 'evidence_validated', 'unverified'
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'severity' => $this->severity,
            'title' => $this->title,
            'documentA' => [
                'page' => $this->docAPage,
                'evidence' => $this->docAEvidence,
            ],
            'documentB' => [
                'page' => $this->docBPage,
                'evidence' => $this->docBEvidence,
            ],
            'explanation' => $this->explanation,
            'confidence' => $this->confidence,
            'verificationStatus' => $this->verificationStatus,
            'metrics' => $this->metrics,
        ];
    }
}
