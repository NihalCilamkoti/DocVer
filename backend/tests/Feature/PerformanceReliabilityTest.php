<?php

namespace Tests\Feature;

use App\Enums\ComparisonStatus;
use App\Enums\UserRole;
use App\Models\ComparisonFinding;
use App\Models\ComparisonJob;
use App\Models\ComparisonReport;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Reports\PdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PerformanceReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;
    protected ComparisonJob $job;
    protected ComparisonReport $report;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Performance Engineer',
            'email' => 'perf@docver.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::WORKSPACE_ADMIN,
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Load Test Workspace',
            'slug' => 'load-ws',
            'owner_id' => $this->user->id,
            'plan' => 'enterprise',
            'max_file_size_mb' => 50,
        ]);
        $this->workspace->members()->attach($this->user->id, ['role' => 'admin']);
        $this->token = $this->user->createToken('perf-token')->plainTextToken;

        $this->job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Large Multi-Clause Audit',
            'status' => ComparisonStatus::COMPLETED,
            'similarity_score' => 88,
        ]);

        $this->report = ComparisonReport::create([
            'comparison_job_id' => $this->job->id,
            'total_differences' => 20,
            'high_risk_count' => 5,
            'medium_risk_count' => 10,
            'low_risk_count' => 5,
            'matching_clauses_count' => 45,
            'missing_clauses_count' => 3,
            'modified_clauses_count' => 12,
            'numerical_discrepancies_count' => 5,
            'contradictions_count' => 2,
            'executive_summary' => 'Comprehensive enterprise redline report with multi-clause findings across 50 pages.',
            'generated_at' => now(),
        ]);

        // Insert 20 sample findings
        for ($i = 1; $i <= 20; $i++) {
            ComparisonFinding::create([
                'comparison_report_id' => $this->report->id,
                'type' => ($i % 3 === 0) ? 'numerical' : 'modified',
                'risk_level' => ($i <= 5) ? 'high' : 'medium',
                'category' => 'Financial Provisions',
                'title' => "Clause Discrepancy #{$i}",
                'doc_a_page' => $i,
                'doc_b_page' => $i + 1,
                'explanation' => "Variation detected in subclause {$i} regarding financial obligations.",
                'significance' => 'High contractual variance.',
                'order_index' => $i,
            ]);
        }
    }

    public function test_pdf_report_generates_valid_pdf_binary_under_sla(): void
    {
        $pdfService = app(PdfReportService::class);

        $start = microtime(true);
        $response = $pdfService->generatePdfResponse($this->job, $this->report);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));

        // Assert valid PDF magic bytes in generated binary output
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);

        // Generation of 20-finding multi-page report must complete in reasonable time
        $this->assertLessThan(2500, $durationMs, "PDF generation took {$durationMs}ms, exceeding budget.");
    }

    public function test_pdf_report_api_endpoint_downloads_successfully(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->get("/api/v1/comparisons/{$this->job->id}/report/pdf");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_temporary_purge_command_runs_successfully(): void
    {
        $exitCode = Artisan::call('docver:purge-temporary');
        $this->assertEquals(0, $exitCode);
    }
}
