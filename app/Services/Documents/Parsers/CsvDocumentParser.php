<?php

namespace App\Services\Documents\Parsers;

use App\Services\Documents\Contracts\DocumentParserInterface;
use App\Services\Documents\DTOs\ExtractedDocumentDTO;
use App\Services\Documents\DTOs\ExtractedPageDTO;
use App\Services\Documents\DTOs\ExtractedTableDTO;

class CsvDocumentParser implements DocumentParserInterface
{
    private const ROWS_PER_PAGE = 50;

    public function supports(string $extension): bool
    {
        return strtolower($extension) === 'csv';
    }

    public function parse(string $filePath, array $options = []): ExtractedDocumentDTO
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new \RuntimeException("Unable to open CSV file: {$filePath}");
        }

        $headers = fgetcsv($handle);
        $headers = $headers ?: [];

        $pages = [];
        $pageNumber = 1;
        $currentRows = [];
        $totalWordCount = 0;

        foreach ($headers as $h) {
            $totalWordCount += str_word_count((string)$h);
        }

        while (($row = fgetcsv($handle)) !== false) {
            foreach ($row as $col) {
                $totalWordCount += str_word_count((string)$col);
            }
            $currentRows[] = $row;

            if (count($currentRows) >= self::ROWS_PER_PAGE) {
                $table = new ExtractedTableDTO(
                    headers: $headers,
                    rows: $currentRows,
                    title: "CSV Schedule (Page {$pageNumber})",
                    pageNumber: $pageNumber
                );

                $pages[] = new ExtractedPageDTO(
                    pageNumber: $pageNumber++,
                    text: $table->toText(),
                    tables: [$table],
                    sections: ["CSV Page {$pageNumber}"],
                    isScanned: false,
                    wordCount: str_word_count($table->toText())
                );
                $currentRows = [];
            }
        }
        fclose($handle);

        if (! empty($currentRows) || empty($pages)) {
            $table = new ExtractedTableDTO(
                headers: $headers,
                rows: $currentRows,
                title: "CSV Schedule (Page {$pageNumber})",
                pageNumber: $pageNumber
            );

            $pages[] = new ExtractedPageDTO(
                pageNumber: $pageNumber,
                text: $table->toText(),
                tables: [$table],
                sections: ["CSV Page {$pageNumber}"],
                isScanned: false,
                wordCount: str_word_count($table->toText())
            );
        }

        return new ExtractedDocumentDTO(
            originalName: basename($filePath),
            format: 'csv',
            totalPages: count($pages),
            pages: $pages,
            metadata: ['columns' => count($headers)],
            totalWordCount: $totalWordCount
        );
    }
}
