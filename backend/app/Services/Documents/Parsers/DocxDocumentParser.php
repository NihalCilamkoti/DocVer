<?php

namespace App\Services\Documents\Parsers;

use App\Services\Documents\Contracts\DocumentParserInterface;
use App\Services\Documents\DTOs\ExtractedDocumentDTO;
use App\Services\Documents\DTOs\ExtractedPageDTO;
use App\Services\Documents\DTOs\ExtractedTableDTO;
use App\Services\Documents\Security\ZipBombGuard;
use ZipArchive;
use SimpleXMLElement;

class DocxDocumentParser implements DocumentParserInterface
{
    public function __construct(
        protected ZipBombGuard $zipBombGuard = new ZipBombGuard()
    ) {}

    public function supports(string $extension): bool
    {
        return strtolower($extension) === 'docx';
    }

    public function parse(string $filePath, array $options = []): ExtractedDocumentDTO
    {
        $this->zipBombGuard->inspect($filePath);

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException("Unable to open DOCX package.");
        }

        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! $documentXml) {
            throw new \RuntimeException("Corrupt DOCX: word/document.xml missing.");
        }

        $pages = [];
        $currentPageNumber = 1;
        $currentPageText = [];
        $currentPageTables = [];
        $currentPageSections = [];
        $totalWords = 0;

        $xml = simplexml_load_string($documentXml, SimpleXMLElement::class, LIBXML_NOENT);
        $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $bodyElements = $xml->xpath('//w:body/*');

        foreach ($bodyElements as $elem) {
            $name = $elem->getName();

            // Paragraph
            if ($name === 'p') {
                $textPieces = $elem->xpath('.//w:t');
                $pText = '';
                foreach ($textPieces as $t) {
                    $pText .= (string) $t;
                }
                $pText = trim($pText);

                if (! empty($pText)) {
                    $currentPageText[] = $pText;
                    $totalWords += str_word_count($pText);

                    // Check for section headings
                    if (preg_match('/^(section|article|clause|\d+\.|\b[A-Z\s]{4,}\b)/i', $pText) && strlen($pText) < 100) {
                        $currentPageSections[] = $pText;
                    }
                }

                // Check for explicit page break in paragraph
                $pageBreaks = $elem->xpath('.//w:br[@w:type="page"]') ?: $elem->xpath('.//w:lastRenderedPageBreak');
                if (! empty($pageBreaks)) {
                    $pages[] = new ExtractedPageDTO(
                        pageNumber: $currentPageNumber,
                        text: implode("\n\n", $currentPageText),
                        tables: $currentPageTables,
                        sections: $currentPageSections,
                        isScanned: false,
                        wordCount: str_word_count(implode(" ", $currentPageText))
                    );

                    $currentPageNumber++;
                    $currentPageText = [];
                    $currentPageTables = [];
                    $currentPageSections = [];
                }
            }

            // Table
            elseif ($name === 'tbl') {
                $rows = [];
                $headers = [];
                $tableRows = $elem->xpath('.//w:tr');

                foreach ($tableRows as $rIdx => $row) {
                    $cells = [];
                    foreach ($row->xpath('.//w:tc') as $cell) {
                        $cText = '';
                        foreach ($cell->xpath('.//w:t') as $t) {
                            $cText .= (string) $t;
                        }
                        $cells[] = trim($cText);
                    }

                    if ($rIdx === 0) {
                        $headers = $cells;
                    } else {
                        $rows[] = $cells;
                    }
                }

                if (! empty($headers) || ! empty($rows)) {
                    $tableDTO = new ExtractedTableDTO(
                        headers: $headers,
                        rows: $rows,
                        pageNumber: $currentPageNumber
                    );
                    $currentPageTables[] = $tableDTO;
                    $currentPageText[] = $tableDTO->toText();
                }
            }
        }

        // Flush final page
        if (! empty($currentPageText) || empty($pages)) {
            $pages[] = new ExtractedPageDTO(
                pageNumber: $currentPageNumber,
                text: implode("\n\n", $currentPageText),
                tables: $currentPageTables,
                sections: $currentPageSections,
                isScanned: false,
                wordCount: str_word_count(implode(" ", $currentPageText))
            );
        }

        return new ExtractedDocumentDTO(
            originalName: basename($filePath),
            format: 'docx',
            totalPages: count($pages),
            pages: $pages,
            metadata: [],
            totalWordCount: $totalWords
        );
    }
}
