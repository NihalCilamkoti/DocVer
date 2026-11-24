<?php

namespace App\Services\Documents\Contracts;

use App\Services\Documents\DTOs\ExtractedDocumentDTO;

interface DocumentParserInterface
{
    /**
     * Parse document and return extracted pages, preserving page boundaries,
     * section titles, and tables.
     */
    public function parse(string $filePath, array $options = []): ExtractedDocumentDTO;

    /**
     * Check if this parser supports the given format extension.
     */
    public function supports(string $extension): bool;
}
