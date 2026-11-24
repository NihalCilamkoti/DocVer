<?php

namespace Tests\Unit;

use App\Services\Documents\DTOs\ExtractedDocumentDTO;
use App\Services\Documents\DTOs\ExtractedPageDTO;
use App\Services\Documents\Indexing\DocumentChunker;
use App\Services\Documents\Indexing\EphemeralIndexManager;
use App\Services\Documents\Parsers\CsvDocumentParser;
use App\Services\Documents\Parsers\TextDocumentParser;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DocumentProcessingComponentsTest extends TestCase
{
    public function test_text_parser_extracts_pages_and_lines(): void
    {
        $parser = new TextDocumentParser();
        $tmp = tempnam(sys_get_temp_dir(), 'txt_test');

        // Create 3 simulated pages using form feed \f
        $content = "Page 1: Section 1.0 General Provisions\nContract details here.\fPage 2: Section 2.0 Payment Terms\nAmount is $50,000.\fPage 3: Section 3.0 Signatures\nSigned by Parties.";
        file_put_contents($tmp, $content);

        $extracted = $parser->parse($tmp);
        unlink($tmp);

        $this->assertEquals(3, $extracted->totalPages);
        $this->assertEquals(1, $extracted->pages[0]->pageNumber);
        $this->assertStringContainsString('General Provisions', $extracted->pages[0]->text);
        $this->assertEquals(2, $extracted->pages[1]->pageNumber);
        $this->assertStringContainsString('Payment Terms', $extracted->pages[1]->text);
    }

    public function test_csv_parser_extracts_structured_tables(): void
    {
        $parser = new CsvDocumentParser();
        $tmp = tempnam(sys_get_temp_dir(), 'csv_table_test');

        $csvData = "Clause,Obligation,Penalty\nConfidentiality,5 Years,10000\nIndemnity,Unlimited,50000\n";
        file_put_contents($tmp, $csvData);

        $extracted = $parser->parse($tmp);
        unlink($tmp);

        $this->assertGreaterThanOrEqual(1, $extracted->totalPages);
        $this->assertNotEmpty($extracted->pages[0]->tables);
        $table = $extracted->pages[0]->tables[0];
        $this->assertEquals(['Clause', 'Obligation', 'Penalty'], $table->headers);
        $this->assertCount(2, $table->rows);
    }

    public function test_chunker_preserves_page_boundaries_and_citations(): void
    {
        $chunker = new DocumentChunker(chunkSize: 200, chunkOverlap: 40);

        $page1 = new ExtractedPageDTO(
            pageNumber: 1,
            text: "Article 1: Scope of Work. The contractor agrees to supply engineering deliverables in accordance with Schedule A.",
            tables: [],
            sections: ['Article 1: Scope of Work'],
            wordCount: 16
        );

        $page2 = new ExtractedPageDTO(
            pageNumber: 2,
            text: "Article 2: Fee Schedule. Payment shall be released within 30 days of receiving invoices.",
            tables: [],
            sections: ['Article 2: Fee Schedule'],
            wordCount: 13
        );

        $doc = new ExtractedDocumentDTO(
            originalName: 'ServiceAgreement.pdf',
            format: 'pdf',
            totalPages: 2,
            pages: [$page1, $page2],
            totalWordCount: 29
        );

        $chunks = $chunker->chunk($doc, documentId: 101, slot: 'A');

        $this->assertNotEmpty($chunks);
        $this->assertEquals(1, $chunks[0]->pageNumber);
        $this->assertEquals('A', $chunks[0]->documentSlot);
        $this->assertEquals('Article 1: Scope of Work', $chunks[0]->sectionTitle);
        $this->assertEquals(101, $chunks[0]->documentId);
    }

    public function test_ephemeral_index_manager_stores_and_clears_chunks(): void
    {
        $indexManager = new EphemeralIndexManager();
        $jobId = 9999;

        $chunker = new DocumentChunker();
        $page = new ExtractedPageDTO(1, "Confidentiality clause test", [], ['Section 1'], false, 3);
        $doc = new ExtractedDocumentDTO('Test.pdf', 'pdf', 1, [$page], [], 3);
        $chunks = $chunker->chunk($doc, 1, 'A');

        $indexManager->storeChunks($jobId, 'A', $chunks);

        $this->assertTrue($indexManager->hasIndex($jobId));
        $retrieved = $indexManager->getChunks($jobId, 'A');
        $this->assertCount(count($chunks), $retrieved);
        $this->assertEquals('A', $retrieved[0]['slot']);

        // Clear index
        $indexManager->clearIndex($jobId);
        $this->assertFalse($indexManager->hasIndex($jobId));
        $this->assertEmpty($indexManager->getChunks($jobId, 'A'));
    }
}
