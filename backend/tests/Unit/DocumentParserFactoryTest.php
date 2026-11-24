<?php

namespace Tests\Unit;

use App\Services\Documents\Parsers\CsvDocumentParser;
use App\Services\Documents\Parsers\DocxDocumentParser;
use App\Services\Documents\Parsers\DocumentParserFactory;
use App\Services\Documents\Parsers\PdfDocumentParser;
use App\Services\Documents\Parsers\TextDocumentParser;
use PHPUnit\Framework\TestCase;

class DocumentParserFactoryTest extends TestCase
{
    public function test_factory_resolves_all_supported_adapters(): void
    {
        $factory = new DocumentParserFactory();

        $this->assertInstanceOf(PdfDocumentParser::class, $factory->getParser('pdf'));
        $this->assertInstanceOf(DocxDocumentParser::class, $factory->getParser('docx'));
        $this->assertInstanceOf(TextDocumentParser::class, $factory->getParser('txt'));
        $this->assertInstanceOf(CsvDocumentParser::class, $factory->getParser('csv'));
    }

    public function test_factory_rejects_unsupported_extensions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $factory = new DocumentParserFactory();
        $factory->getParser('exe');
    }
}
