<?php

namespace App\Services\Documents\Parsers;

use App\Services\Documents\Contracts\DocumentParserInterface;
use App\Services\Documents\DTOs\ExtractedDocumentDTO;
use App\Services\Documents\DTOs\ExtractedPageDTO;

class DocDocumentParser implements DocumentParserInterface
{
    public function supports(string $extension): bool
    {
        return strtolower($extension) === 'doc';
    }

    public function parse(string $filePath, array $options = []): ExtractedDocumentDTO
    {
        $content = file_get_contents($filePath);
        $extractedText = '';

        // Extract printable Unicode / ASCII fragments from WordDocument binary stream
        if (preg_match_all('/[\x20-\x7E\xA0-\xFF]{4,}/', $content, $matches)) {
            $filtered = array_filter($matches[0], function ($str) {
                return ! preg_match('/^(root|worddocument|compobj|summaryinformation|table)/i', $str);
            });
            $extractedText = implode("\n", $filtered);
        }

        $page = new ExtractedPageDTO(
            pageNumber: 1,
            text: $extractedText,
            tables: [],
            sections: ['Document Body'],
            isScanned: false,
            wordCount: str_word_count($extractedText)
        );

        return new ExtractedDocumentDTO(
            originalName: basename($filePath),
            format: 'doc',
            totalPages: 1,
            pages: [$page],
            metadata: ['legacy_format' => true],
            totalWordCount: $page->wordCount
        );
    }
}
