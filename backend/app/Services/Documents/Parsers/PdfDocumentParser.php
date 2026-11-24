<?php

namespace App\Services\Documents\Parsers;

use App\Services\Documents\Contracts\DocumentParserInterface;
use App\Services\Documents\DTOs\ExtractedDocumentDTO;
use App\Services\Documents\DTOs\ExtractedPageDTO;
use Smalot\PdfParser\Parser as SmalotParser;

class PdfDocumentParser implements DocumentParserInterface
{
    public function supports(string $extension): bool
    {
        return strtolower($extension) === 'pdf';
    }

    public function parse(string $filePath, array $options = []): ExtractedDocumentDTO
    {
        $parser = new SmalotParser();
        $pdf = $parser->parseFile($filePath);
        $smalotPages = $pdf->getPages();

        $extractedPages = [];
        $totalWords = 0;
        $pageNumber = 1;

        foreach ($smalotPages as $p) {
            $rawText = trim($p->getText());
            $wordCount = str_word_count($rawText);
            $totalWords += $wordCount;

            // If a page has virtually zero extracted text, flag as potentially scanned
            $isScanned = $wordCount < 10;

            // Detect section headers from beginning of page
            $sections = [];
            $lines = explode("\n", $rawText);
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (preg_match('/^(section|article|clause|\d+\.|\b[A-Z\s]{4,}\b)/i', $trimmed) && strlen($trimmed) < 100) {
                    $sections[] = $trimmed;
                }
            }

            $extractedPages[] = new ExtractedPageDTO(
                pageNumber: $pageNumber,
                text: $rawText,
                tables: [],
                sections: array_slice($sections, 0, 5),
                isScanned: $isScanned,
                wordCount: $wordCount
            );

            $pageNumber++;
        }

        return new ExtractedDocumentDTO(
            originalName: basename($filePath),
            format: 'pdf',
            totalPages: count($extractedPages),
            pages: $extractedPages,
            metadata: $pdf->getDetails(),
            totalWordCount: $totalWords
        );
    }
}
