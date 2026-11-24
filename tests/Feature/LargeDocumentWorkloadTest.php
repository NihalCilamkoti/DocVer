<?php

namespace Tests\Feature;

use App\Services\Comparison\DocumentStructureAnalyzer;
use App\Services\Comparison\DTOs\AnalyzedSectionDTO;
use App\Services\Comparison\SectionMatcher;
use App\Services\Comparison\SemanticRetrievalService;
use Tests\TestCase;

class LargeDocumentWorkloadTest extends TestCase
{
    public function test_100_page_document_structure_and_matching_latency(): void
    {
        $analyzer = new DocumentStructureAnalyzer();
        $retrieval = new SemanticRetrievalService();
        $matcher = new SectionMatcher($retrieval);

        // Generate 100 pages of simulated legal chunks
        $chunksA = [];
        $chunksB = [];

        for ($p = 1; $p <= 100; $p++) {
            $clauseNum = (string)$p;
            $chunksA[] = [
                'page_number' => $p,
                'section_title' => "Clause {$clauseNum}.0 Standard Covenant {$p}",
                'content' => "Clause {$clauseNum}.0 Standard Covenant {$p}\nParty A obligations and specifications under subclause {$p}.",
            ];

            // In Doc B, introduce slight modifications every 10 pages
            $modifiedText = ($p % 10 === 0)
                ? "Clause {$clauseNum}.0 Standard Covenant {$p}\nParty A obligations are modified to Net 30 days."
                : "Clause {$clauseNum}.0 Standard Covenant {$p}\nParty A obligations and specifications under subclause {$p}.";

            $chunksB[] = [
                'page_number' => $p,
                'section_title' => "Clause {$clauseNum}.0 Standard Covenant {$p}",
                'content' => $modifiedText,
            ];
        }

        $start = microtime(true);

        $sectionsA = $analyzer->analyzeStructure($chunksA, 'A');
        $sectionsB = $analyzer->analyzeStructure($chunksB, 'B');

        $this->assertCount(100, $sectionsA);
        $this->assertCount(100, $sectionsB);

        $matchResult = $matcher->matchSections($sectionsA, $sectionsB);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        // Verify all 100 sections matched
        $this->assertCount(100, $matchResult['matches']);
        $this->assertEmpty($matchResult['unmatchedA']);
        $this->assertEmpty($matchResult['unmatchedB']);

        // Must complete well within 500ms
        $this->assertLessThan(500, $durationMs, "100-page structure analysis and matching took {$durationMs}ms, exceeding threshold.");
    }

    public function test_1000_page_semantic_candidate_retrieval_scalability(): void
    {
        $retrieval = new SemanticRetrievalService();

        // Generate 1,000 simulated sections
        $sections = [];
        for ($i = 1; $i <= 1000; $i++) {
            $title = match(true) {
                $i === 420 => 'Article 42.0 Comprehensive Indemnification and Liability Cap',
                $i === 780 => 'Section 78.0 Master Payment and Invoicing Schedule',
                default => "Section {$i}.0 General Operational Covenant {$i}",
            };

            $sections[] = new AnalyzedSectionDTO(
                id: "sec_{$i}",
                slot: 'B',
                title: $title,
                clauseNumber: (string)$i,
                pageStart: $i,
                pageEnd: $i,
                content: "Content provisions for section {$i} relating to services and obligations.",
                category: 'General'
            );
        }

        $start = microtime(true);

        // Perform targeted candidate retrieval for a complex query
        $topCandidates = $retrieval->retrieveCandidates(
            query: "Indemnification and liability cap limitation",
            candidates: $sections,
            topK: 5
        );

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $this->assertNotEmpty($topCandidates);
        $this->assertEquals('Article 42.0 Comprehensive Indemnification and Liability Cap', $topCandidates[0]['section']->title);

        // Must retrieve from 1,000 sections within 100ms
        $this->assertLessThan(100, $durationMs, "1,000-page candidate retrieval took {$durationMs}ms, exceeding 100ms budget.");
    }
}
