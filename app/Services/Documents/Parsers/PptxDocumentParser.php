<?php

namespace App\Services\Documents\Parsers;

use App\Services\Documents\Contracts\DocumentParserInterface;
use App\Services\Documents\DTOs\ExtractedDocumentDTO;
use App\Services\Documents\DTOs\ExtractedPageDTO;
use App\Services\Documents\DTOs\ExtractedTableDTO;
use App\Services\Documents\Security\ZipBombGuard;
use ZipArchive;
use SimpleXMLElement;

class PptxDocumentParser implements DocumentParserInterface
{
    public function __construct(
        protected ZipBombGuard $zipBombGuard = new ZipBombGuard()
    ) {}

    public function supports(string $extension): bool
    {
        return strtolower($extension) === 'pptx';
    }

    public function parse(string $filePath, array $options = []): ExtractedDocumentDTO
    {
        $this->zipBombGuard->inspect($filePath);

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException("Unable to open PPTX presentation package.");
        }

        $pages = [];
        $totalWords = 0;

        // Slide numbering: 1 slide = 1 page
        for ($slideNum = 1; $slideNum <= 250; $slideNum++) {
            $slideXml = $zip->getFromName("ppt/slides/slide{$slideNum}.xml");
            if (! $slideXml) break;

            $sXml = simplexml_load_string($slideXml, SimpleXMLElement::class, LIBXML_NOENT);
            $sXml->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

            $textPieces = $sXml->xpath('//a:t');
            $slideText = [];
            $slideTitle = "Slide {$slideNum}";

            foreach ($textPieces as $idx => $t) {
                $val = trim((string) $t);
                if (! empty($val)) {
                    if ($idx === 0) {
                        $slideTitle = $val;
                    }
                    $slideText[] = $val;
                    $totalWords += str_word_count($val);
                }
            }

            $combined = implode("\n", $slideText);
            $pages[] = new ExtractedPageDTO(
                pageNumber: $slideNum,
                text: $combined,
                tables: [],
                sections: [$slideTitle],
                isScanned: false,
                wordCount: str_word_count($combined)
            );
        }

        $zip->close();

        return new ExtractedDocumentDTO(
            originalName: basename($filePath),
            format: 'pptx',
            totalPages: count($pages),
            pages: $pages,
            metadata: ['slide_count' => count($pages)],
            totalWordCount: $totalWords
        );
    }
}
