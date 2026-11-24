<?php

namespace App\Services\Documents\DTOs;

readonly class FileSignatureValidationResult
{
    public ?string $detectedFormat;

    public function __construct(
        public bool $isValid,
        public ?string $detectedExtension = null,
        public ?string $mimeType = null,
        public ?string $errorMessage = null,
    ) {
        $this->detectedFormat = $detectedExtension;
    }

    public static function valid(string $extension, string $mimeType): self
    {
        return new self(
            isValid: true,
            detectedExtension: strtolower($extension),
            mimeType: $mimeType,
        );
    }

    public static function invalid(string $errorMessage): self
    {
        return new self(
            isValid: false,
            errorMessage: $errorMessage,
        );
    }
}
