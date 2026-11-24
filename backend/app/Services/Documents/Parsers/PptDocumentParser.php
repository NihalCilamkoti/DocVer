<?php

namespace App\Services\Documents\Parsers;

use App\Services\Documents\Contracts\DocumentParserInterface;
use App\Services\Documents\DTOs\ExtractedDocumentDTO;
use App\Services\Documents\DTOs\ExtractedPageDTO;

class PptDocumentParser implements DocumentParserInterface
{
    public function supports(string $extension): bool
    {
        return strtolower($extension) === 'ppt';
    }

    public function parse(string $filePath, array $options = []): ExtractedDocumentDTO
    {
        $content = file_get_contents($filePath);
        $text = '';
        if (preg_match_all('/[\x20-\x7E]{4,}/', $content, $matches)) {
            $filtered = array_filter($matches[0], fn($str) => ! preg_match('/^(powerpoint|microsoft|summary|fonts)/i', $str));
            $text = implode("\n", $filtered);
        }

        $page = new ExtractedPageDTO(
            pageNumber: 1,
            text: $text,
            tables: [],
            sections: ['Presentation Slides'],
            isScanned: false,
            wordCount: str_word_count($text)
        );

        return new ExtractedDocumentDTO(
            originalName: basename($filePath),
            format: 'ppt',
            totalPages: 1,
            pages: [$page],
            metadata: ['legacy_format' => true],
            totalWordCount: $page->wordCount
        );
    }
}
