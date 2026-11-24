<?php

namespace Tests\Unit;

use App\Services\Comparison\DateComparisonService;
use App\Services\Comparison\DTOs\AnalyzedSectionDTO;
use App\Services\Comparison\NumericalComparisonService;
use PHPUnit\Framework\TestCase;

class NumericalAndDateComparisonTest extends TestCase
{
    protected NumericalComparisonService $numService;
    protected DateComparisonService $dateService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->numService = new NumericalComparisonService();
        $this->dateService = new DateComparisonService();
    }

    public function test_currency_formatting_is_not_flagged_as_difference(): void
    {
        // ₹10,000 vs INR 10,000 should NOT be a discrepancy
        $secA = new AnalyzedSectionDTO(
            id: 'a1', slot: 'A', title: 'Payment',
            content: "Consultant fee is ₹10,000 payable upon invoice."
        );
        $secB = new AnalyzedSectionDTO(
            id: 'b1', slot: 'B', title: 'Payment',
            content: "Consultant fee is INR 10,000 payable upon invoice."
        );

        $findings = $this->numService->compareNumerics($secA, $secB);
        $this->assertEmpty($findings, "Formatting variation (₹10,000 vs INR 10,000) should normalize to identical value.");
    }

    public function test_detects_substantive_financial_discrepancy(): void
    {
        $secA = new AnalyzedSectionDTO(
            id: 'a2', slot: 'A', title: 'Liability Cap',
            content: "Total aggregate liability is capped at \$2,000,000 USD."
        );
        $secB = new AnalyzedSectionDTO(
            id: 'b2', slot: 'B', title: 'Liability Cap',
            content: "Total aggregate liability is capped at \$1,000,000 USD."
        );

        $findings = $this->numService->compareNumerics($secA, $secB);
        $this->assertCount(1, $findings);
        $this->assertEquals('financial', $findings[0]->type);
        $this->assertEquals('high', $findings[0]->severity);
        $this->assertEquals(-50.0, $findings[0]->metrics['variance_percent']);
    }

    public function test_detects_changed_payment_timeline_duration(): void
    {
        $secA = new AnalyzedSectionDTO(
            id: 'a3', slot: 'A', title: 'Invoicing Terms',
            content: "All undisputed invoices shall be paid Net 60 days."
        );
        $secB = new AnalyzedSectionDTO(
            id: 'b3', slot: 'B', title: 'Invoicing Terms',
            content: "All undisputed invoices shall be paid Net 30 days."
        );

        $findings = $this->dateService->compareDates($secA, $secB);
        $this->assertCount(1, $findings);
        $this->assertEquals('modified', $findings[0]->type);
        $this->assertEquals('Dates & Deadlines', $findings[0]->category);
        $this->assertStringContainsString('Net 60', $findings[0]->docAEvidence);
        $this->assertStringContainsString('Net 30', $findings[0]->docBEvidence);
    }
}
