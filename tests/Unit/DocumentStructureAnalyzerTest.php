<?php

namespace Tests\Unit;

use App\Services\Comparison\DocumentStructureAnalyzer;
use PHPUnit\Framework\TestCase;

class DocumentStructureAnalyzerTest extends TestCase
{
    protected DocumentStructureAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new DocumentStructureAnalyzer();
    }

    public function test_groups_chunks_into_sections_with_categories(): void
    {
        $chunks = [
            [
                'page_number' => 1,
                'section_title' => 'Article 1.0 Scope of Work',
                'content' => "Article 1.0 Scope of Work\nContractor shall supply software deliverables.",
            ],
            [
                'page_number' => 2,
                'section_title' => 'Article 1.0 Scope of Work',
                'content' => "Deliverables must comply with Schedule A specifications.",
            ],
            [
                'page_number' => 3,
                'section_title' => 'Article 2.0 Indemnification and Liability',
                'content' => "Article 2.0 Indemnification and Liability\nVendor shall indemnify client up to \$1,000,000.",
            ],
        ];

        $sections = $this->analyzer->analyzeStructure($chunks, 'A');

        $this->assertCount(2, $sections);
        $this->assertEquals('Article 1.0 Scope of Work', $sections[0]->title);
        $this->assertEquals('1.0', $sections[0]->clauseNumber);
        $this->assertEquals('Scope of Work', $sections[0]->category);
        $this->assertEquals(1, $sections[0]->pageStart);
        $this->assertEquals(2, $sections[0]->pageEnd);

        $this->assertEquals('Article 2.0 Indemnification and Liability', $sections[1]->title);
        $this->assertEquals('2.0', $sections[1]->clauseNumber);
        $this->assertEquals('Indemnification & Liability', $sections[1]->category);
    }
}
