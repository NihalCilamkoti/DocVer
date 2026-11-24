<?php

namespace App\Services\Documents\Parsers;

use App\Services\Documents\Contracts\DocumentParserInterface;
use App\Services\Documents\DTOs\ExtractedDocumentDTO;
use App\Services\Documents\DTOs\ExtractedPageDTO;

class TextDocumentParser implements DocumentParserInterface
{
    private const LINES_PER_PAGE = 45;

    public function supports(string $extension): bool
    {
        return strtolower($extension) === 'txt';
    }

    public function parse(string $filePath, array $options = []): ExtractedDocumentDTO
    {
        $file = new \SplFileObject($filePath, 'r');
        $pages = [];
        $pageNumber = 1;
        $currentLines = [];
        $totalWordCount = 0;

        while (! $file->eof()) {
            $line = $file->fgets();
            $totalWordCount += str_word_count($line);

            // Form feed indicates physical page break
            if (str_contains($line, "\x0C")) {
                $parts = explode("\x0C", $line);
                $currentLines[] = $parts[0];

                $pageText = implode("", $currentLines);
                $pages[] = new ExtractedPageDTO(
                    pageNumber: $pageNumber++,
                    text: trim($pageText),
                    tables: [],
                    sections: $this->detectSections($currentLines),
                    isScanned: false,
                    wordCount: str_word_count($pageText)
                );

                $currentLines = [array_slice($parts, 1)[0] ?? ''];
                continue;
            }

            $currentLines[] = $line;

            if (count($currentLines) >= self::LINES_PER_PAGE) {
                $pageText = implode("", $currentLines);
                $pages[] = new ExtractedPageDTO(
                    pageNumber: $pageNumber++,
                    text: trim($pageText),
                    tables: [],
                    sections: $this->detectSections($currentLines),
                    isScanned: false,
                    wordCount: str_word_count($pageText)
                );
                $currentLines = [];
            }
        }

        if (! empty($currentLines) || empty($pages)) {
            $pageText = implode("", $currentLines);
            $pages[] = new ExtractedPageDTO(
                pageNumber: $pageNumber,
                text: trim($pageText),
                tables: [],
                sections: $this->detectSections($currentLines),
                isScanned: false,
                wordCount: str_word_count($pageText)
            );
        }

        return new ExtractedDocumentDTO(
            originalName: basename($filePath),
            format: 'txt',
            totalPages: count($pages),
            pages: $pages,
            metadata: ['line_count' => $file->key()],
            totalWordCount: $totalWordCount
        );
    }

    private function detectSections(array $lines): array
    {
        $sections = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (preg_match('/^(section|article|clause|\d+\.|\b[A-Z\s]{4,}\b)/i', $trimmed) && strlen($trimmed) < 100) {
                $sections[] = $trimmed;
            }
        }
        return array_slice($sections, 0, 5);
    }
}
