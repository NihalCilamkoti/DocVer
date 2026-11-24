<?php

namespace Tests\Unit;

use App\Services\Comparison\DTOs\CandidateFindingDTO;
use App\Services\Comparison\EvidenceValidator;
use PHPUnit\Framework\TestCase;

class EvidenceValidatorTest extends TestCase
{
    protected EvidenceValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new EvidenceValidator();
    }

    public function test_validates_ground_truth_evidence_and_verifies_status(): void
    {
        $chunksA = [
            ['page_number' => 14, 'content' => "Section 14.1 Vendor aggregate liability is capped at \$2,000,000 USD."],
        ];
        $chunksB = [
            ['page_number' => 16, 'content' => "Section 14.1 Vendor aggregate liability is capped at \$1,000,000 USD."],
        ];

        $candidate = new CandidateFindingDTO(
            id: 'finding_1',
            type: 'financial',
            category: 'Indemnification & Liability',
            severity: 'high',
            title: 'Liability cap reduced by 50%',
            docAPage: 14,
            docAEvidence: "Vendor aggregate liability is capped at \$2,000,000",
            docBPage: 16,
            docBEvidence: "Vendor aggregate liability is capped at \$1,000,000",
            explanation: 'Cap was lowered by $1M.',
            confidence: 0.98,
            verificationStatus: 'candidate'
        );

        $validated = $this->validator->validateFindings([$candidate], $chunksA, $chunksB);

        $this->assertCount(1, $validated);
        $this->assertEquals('evidence_validated', $validated[0]->verificationStatus);
        $this->assertEquals(14, $validated[0]->docAPage);
        $this->assertEquals(16, $validated[0]->docBPage);
    }

    public function test_corrects_page_number_if_found_on_different_page(): void
    {
        $chunksA = [
            ['page_number' => 1, 'content' => "Preamble and intro."],
            ['page_number' => 5, 'content' => "Section 5.0 Payment fee is \$50,000 upon deliverable sign-off."],
        ];
        $chunksB = [
            ['page_number' => 5, 'content' => "Section 5.0 Payment fee is \$75,000 upon deliverable sign-off."],
        ];

        // Finding mistakenly claims page 1 for Document A
        $candidate = new CandidateFindingDTO(
            id: 'finding_2',
            type: 'financial',
            category: 'Payment Terms',
            severity: 'medium',
            title: 'Fee increased',
            docAPage: 1, // Claimed page 1
            docAEvidence: "Payment fee is \$50,000 upon deliverable sign-off",
            docBPage: 5,
            docBEvidence: "Payment fee is \$75,000 upon deliverable sign-off",
            explanation: 'Fee increased by $25k.'
        );

        $validated = $this->validator->validateFindings([$candidate], $chunksA, $chunksB);

        $this->assertCount(1, $validated);
        // Corrected page number to actual page 5
        $this->assertEquals(5, $validated[0]->docAPage);
    }

    public function test_discards_hallucinated_finding_lacking_evidence_in_source(): void
    {
        $chunksA = [
            ['page_number' => 1, 'content' => "Standard consulting contract terms."],
        ];
        $chunksB = [
            ['page_number' => 1, 'content' => "Standard consulting contract terms."],
        ];

        // Finding with hallucinated quote not present in source text
        $candidate = new CandidateFindingDTO(
            id: 'hallucination_1',
            type: 'modified',
            category: 'Secret Clauses',
            severity: 'high',
            title: 'Fake finding',
            docAPage: 1,
            docAEvidence: "Fabricated clause text that does not exist in document.",
            docBPage: 1,
            docBEvidence: "Another fabricated text.",
            explanation: 'Invented change.'
        );

        $validated = $this->validator->validateFindings([$candidate], $chunksA, $chunksB);

        $this->assertEmpty($validated, "Findings with non-existent source evidence must be rejected.");
    }
}
