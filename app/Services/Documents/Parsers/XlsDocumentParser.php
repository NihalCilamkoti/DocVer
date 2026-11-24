<?php

namespace App\Services\Documents\Parsers;

use App\Services\Documents\Contracts\DocumentParserInterface;
use App\Services\Documents\DTOs\ExtractedDocumentDTO;
use App\Services\Documents\DTOs\ExtractedPageDTO;
use App\Services\Documents\DTOs\ExtractedTableDTO;

class XlsDocumentParser implements DocumentParserInterface
{
    public function supports(string $extension): bool
    {
        return strtolower($extension) === 'xls';
    }

    public function parse(string $filePath, array $options = []): ExtractedDocumentDTO
    {
        $content = file_get_contents($filePath);
        // Extract UTF-16LE / ASCII text chunks from binary BIFF stream
        $text = '';
        if (preg_match_all('/[\x20-\x7E\xA0-\xFF]{4,}/', $content, $matches)) {
            $filtered = array_filter($matches[0], fn($str) => ! preg_match('/^(root|workbook|summary|author|microsoft)/i', $str));
            $text = implode("\n", $filtered);
        }

        $rows = array_map(fn($line) => explode("\t", $line), explode("\n", $text));
        $table = new ExtractedTableDTO(
            headers: $rows[0] ?? [],
            rows: array_slice($rows, 1, 50),
            title: 'Extracted Legacy XLS Records'
        );

        $page = new ExtractedPageDTO(
            pageNumber: 1,
            text: $table->toText(),
            tables: [$table],
            sections: ['Workbook Data'],
            isScanned: false,
            wordCount: str_word_count($table->toText())
        );

        return new ExtractedDocumentDTO(
            originalName: basename($filePath),
            format: 'xls',
            totalPages: 1,
            pages: [$page],
            metadata: ['legacy_format' => true],
            totalWordCount: $page->wordCount
        );
    }
}
