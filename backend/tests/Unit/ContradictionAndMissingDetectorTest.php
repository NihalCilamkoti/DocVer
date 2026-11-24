<?php

namespace Tests\Unit;

use App\Services\Comparison\ContradictionDetector;
use App\Services\Comparison\DTOs\AnalyzedSectionDTO;
use App\Services\Comparison\MissingSectionDetector;
use PHPUnit\Framework\TestCase;

class ContradictionAndMissingDetectorTest extends TestCase
{
    public function test_detects_missing_and_added_clauses(): void
    {
        $detector = new MissingSectionDetector();

        $unmatchedA = [
            new AnalyzedSectionDTO(
                id: 'sec_a_ip', slot: 'A', title: 'Section 19: Work-For-Hire Assignment',
                clauseNumber: '19', pageStart: 26, pageEnd: 27,
                content: "All intellectual property created shall be deemed work-for-hire and assigned to client.",
                category: 'Intellectual Property'
            ),
        ];

        $unmatchedB = [
            new AnalyzedSectionDTO(
                id: 'sec_b_audit', slot: 'B', title: 'Section 22: Annual Security Audits',
                clauseNumber: '22', pageStart: 31, pageEnd: 31,
                content: "Vendor shall undergo annual SOC 2 Type II security audits.",
                category: 'General Provisions'
            ),
        ];

        $findings = $detector->detectMissingAndAdded($unmatchedA, $unmatchedB);

        $this->assertCount(2, $findings);
        $this->assertEquals('missing', $findings[0]->type);
        $this->assertEquals('high', $findings[0]->severity);
        $this->assertEquals(26, $findings[0]->docAPage);

        $this->assertEquals('added', $findings[1]->type);
        $this->assertEquals(31, $findings[1]->docBPage);
    }

    public function test_detects_direct_legal_contradictions(): void
    {
        $detector = new ContradictionDetector();

        $secA = new AnalyzedSectionDTO(
            id: 'sec_a', slot: 'A', title: 'Indemnification',
            pageStart: 12,
            content: "Vendor shall indemnify and hold harmless the client from all third-party claims.",
            category: 'Indemnification & Liability'
        );

        $secB = new AnalyzedSectionDTO(
            id: 'sec_b', slot: 'B', title: 'Indemnification',
            pageStart: 14,
            content: "Neither party shall indemnify or be liable for third-party intellectual property infringement.",
            category: 'Indemnification & Liability'
        );

        $findings = $detector->detectContradictions($secA, $secB);

        $this->assertCount(1, $findings);
        $this->assertEquals('contradiction', $findings[0]->type);
        $this->assertEquals('high', $findings[0]->severity);
        $this->assertStringContainsString('shall indemnify', $findings[0]->docAEvidence);
        $this->assertStringContainsString('Neither party shall', $findings[0]->docBEvidence);
    }
}
