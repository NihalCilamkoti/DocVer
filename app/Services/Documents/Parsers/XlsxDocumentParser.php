<?php

namespace App\Services\Documents\Parsers;

use App\Services\Documents\Contracts\DocumentParserInterface;
use App\Services\Documents\DTOs\ExtractedDocumentDTO;
use App\Services\Documents\DTOs\ExtractedPageDTO;
use App\Services\Documents\DTOs\ExtractedTableDTO;
use App\Services\Documents\Security\ZipBombGuard;
use ZipArchive;
use SimpleXMLElement;

class XlsxDocumentParser implements DocumentParserInterface
{
    public function __construct(
        protected ZipBombGuard $zipBombGuard = new ZipBombGuard()
    ) {}

    public function supports(string $extension): bool
    {
        return strtolower($extension) === 'xlsx';
    }

    public function parse(string $filePath, array $options = []): ExtractedDocumentDTO
    {
        $this->zipBombGuard->inspect($filePath);

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException("Unable to open XLSX spreadsheet package.");
        }

        // 1. Read shared strings table if present
        $sharedStrings = [];
        $stringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($stringsXml) {
            $sXml = simplexml_load_string($stringsXml, SimpleXMLElement::class, LIBXML_NOENT);
            foreach ($sXml->si as $item) {
                $sharedStrings[] = (string) ($item->t ?? $item->r->t ?? '');
            }
        }

        // 2. Discover sheets
        $pages = [];
        $pageNumber = 1;
        $totalWords = 0;

        for ($i = 1; $i <= 50; $i++) {
            $sheetXml = $zip->getFromName("xl/worksheets/sheet{$i}.xml");
            if (! $sheetXml) break;

            $sXml = simplexml_load_string($sheetXml, SimpleXMLElement::class, LIBXML_NOENT);
            $rows = [];
            $headers = [];

            $rowNodes = $sXml->xpath('//sheetData/row');
            foreach ($rowNodes as $rIdx => $row) {
                $cells = [];
                foreach ($row->c as $c) {
                    $val = (string) $c->v;
                    $type = (string) $c['t'];

                    if ($type === 's' && isset($sharedStrings[(int)$val])) {
                        $val = $sharedStrings[(int)$val];
                    }
                    $cells[] = trim($val);
                    $totalWords += str_word_count($val);
                }

                if ($rIdx === 0) {
                    $headers = $cells;
                } else {
                    $rows[] = $cells;
                }
            }

            $table = new ExtractedTableDTO(
                headers: $headers,
                rows: $rows,
                title: "Sheet {$i}",
                pageNumber: $pageNumber
            );

            $pages[] = new ExtractedPageDTO(
                pageNumber: $pageNumber++,
                text: $table->toText(),
                tables: [$table],
                sections: ["Sheet {$i}"],
                isScanned: false,
                wordCount: str_word_count($table->toText())
            );
        }

        $zip->close();

        return new ExtractedDocumentDTO(
            originalName: basename($filePath),
            format: 'xlsx',
            totalPages: count($pages),
            pages: $pages,
            metadata: ['sheets_count' => count($pages)],
            totalWordCount: $totalWords
        );
    }
}
