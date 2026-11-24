<?php

namespace App\Services\Documents\Parsers;

use App\Services\Documents\Contracts\DocumentParserInterface;
use App\Services\Documents\Contracts\OCRServiceInterface;
use App\Services\Documents\OCR\OCRService;
use InvalidArgumentException;

class DocumentParserFactory
{
    /** @var DocumentParserInterface[] */
    protected array $parsers;

    public function __construct(?OCRServiceInterface $ocrService = null)
    {
        $ocrService ??= new OCRService();

        $this->parsers = [
            new PdfDocumentParser(),
            new DocxDocumentParser(),
            new DocDocumentParser(),
            new TextDocumentParser(),
            new CsvDocumentParser(),
            new XlsxDocumentParser(),
            new XlsDocumentParser(),
            new PptxDocumentParser(),
            new PptDocumentParser(),
            new ImageDocumentParser($ocrService),
        ];
    }

    public function getParser(string $extension): DocumentParserInterface
    {
        $normalized = strtolower(trim($extension));

        foreach ($this->parsers as $parser) {
            if ($parser->supports($normalized)) {
                return $parser;
            }
        }

        throw new InvalidArgumentException("No parser registered for document format .{$normalized}");
    }
}
