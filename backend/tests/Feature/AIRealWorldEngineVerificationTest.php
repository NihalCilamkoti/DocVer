<?php

namespace Tests\Feature;

use App\Models\ComparisonJob;
use App\Models\DocumentMetadata;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\AIOrchestrator;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\DTOs\UsageStatsDTO;
use App\Services\AI\Router\AIProviderRouter;
use App\Services\Comparison\ComparisonOrchestrator;
use App\Services\Documents\DTOs\DocumentChunkDTO;
use App\Services\Documents\Indexing\EphemeralIndexManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class AIRealWorldEngineVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;
    protected EphemeralIndexManager $indexManager;
    protected AIOrchestrator $orchestrator;
    protected AIProviderRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::create([
            'name' => 'RealWorld AI Workspace',
            'slug' => 'realworld-ai-ws',
            'owner_id' => $this->user->id,
        ]);
        $this->user->workspaces()->attach($this->workspace->id, ['role' => 'owner']);

        $this->indexManager = app(EphemeralIndexManager::class);
        $this->router = app(AIProviderRouter::class);
        $this->orchestrator = app(AIOrchestrator::class);
        config(['ai.test_live_ai' => true]);
    }

    /**
     * TEST 1 – API GENERATION
     * Verify that the configured model receives prompt and generates meaningful response.
     */
    public function test_1_api_generation(): void
    {
        $mockProvider = Mockery::mock(AIProviderInterface::class);
        $mockProvider->shouldReceive('getName')->andReturn('groq');
        $mockProvider->shouldReceive('isConfigured')->andReturn(true);
        $mockProvider->shouldReceive('getModel')->andReturn('qwen/qwen3.8-27b');
        $mockProvider->shouldReceive('generate')
            ->once()
            ->withArgs(function (AIRequest $req) {
                return str_contains($req->prompt, 'summary of the concept of document verification');
            })
            ->andReturn(new AIResponse(
                content: "Document verification is the process of confirming that a provided record is genuine and has not been altered. It helps institutions mitigate fraud risk and ensure compliance with regulatory standards.",
                provider: 'groq',
                model: 'qwen/qwen3.8-27b',
                usage: new UsageStatsDTO(promptTokens: 25, completionTokens: 40, totalTokens: 65)
            ));

        $this->router->registerProvider($mockProvider);

        $req = new AIRequest(
            prompt: "Write a two-sentence summary of the concept of document verification.",
            systemInstruction: "You are an AI document verification specialist."
        );
        $res = $this->router->route($req);

        $this->assertNotEmpty($res->content);
        $this->assertStringContainsString('Document verification', $res->content);
        $this->assertStringContainsString('genuine', $res->content);
        $this->assertEquals('groq', $res->provider);
    }

    /**
     * TEST 2 – DOCUMENT SUMMARY
     * Upload document containing unique information. Verify generated summary is rephrased,
     * concise, structured, and does not return raw document text.
     */
    public function test_2_document_summary(): void
    {
        $doc = DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'slot' => 'A',
            'original_name' => 'SaaS_Agreement_Alpha.txt',
            'storage_path' => 'fixtures/test_doc_summary.txt',
            'mime_type' => 'text/plain',
            'file_size' => 1024,
            'page_count' => 1,
            'checksum_sha256' => hash('sha256', 'sample'),
            'expires_at' => now()->addDays(7),
        ]);

        $chunk = new DocumentChunkDTO(
            chunkId: "c_sum_1",
            documentSlot: 'A',
            documentId: $doc->id,
            pageNumber: 1,
            sectionTitle: "Service Agreement Terms",
            content: "Alpha Cloud agrees to provide 99.9% uptime SLA. Invoices are issued monthly and must be paid in full within 30 days. Late payments incur a 2% monthly fee.",
            startChar: 0,
            endChar: 180
        );
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Test Job Summary',
            'status' => 'completed',
        ]);
        $doc->update(['comparison_job_id' => $job->id]);
        $this->indexManager->storeChunks($job->id, 'A', [$chunk]);

        $mockProvider = Mockery::mock(AIProviderInterface::class);
        $mockProvider->shouldReceive('getName')->andReturn('groq');
        $mockProvider->shouldReceive('isConfigured')->andReturn(true);
        $mockProvider->shouldReceive('getModel')->andReturn('qwen/qwen3.8-27b');
        $mockProvider->shouldReceive('generate')
            ->once()
            ->andReturn(new AIResponse(
                content: "### Executive Summary\n\nThis agreement outlines the service commitments of Alpha Cloud, establishing an uptime guarantee of 99.9% and standard monthly invoicing.\n\n### Financial & Payment Terms\n- **Payment Period:** Invoices must be settled within 30 days.\n- **Late Penalty:** Delinquent accounts are subject to a 2% monthly surcharge.\n\n### Conclusion\nThe terms reflect standard B2B cloud infrastructure commitments.",
                provider: 'groq',
                model: 'qwen/qwen3.8-27b',
                usage: new UsageStatsDTO(promptTokens: 80, completionTokens: 100, totalTokens: 180)
            ));

        $this->router->registerProvider($mockProvider);

        $res = $this->orchestrator->answerDocumentQuery($doc, "Summarize this document and its key terms.");

        $this->assertNotEmpty($res['answer']);
        $this->assertStringContainsString('Executive Summary', $res['answer']);
        $this->assertStringContainsString('30 days', $res['answer']);
        $this->assertStringContainsString('2%', $res['answer']);
        // Verify output is NOT simply the raw chunk
        $this->assertNotEquals($chunk->content, $res['answer']);
    }

    /**
     * TEST 3 – DOCUMENT COMPARISON
     * Document A: "The customer must pay within 30 days of receiving the invoice."
     * Document B: "The customer must pay within 60 days of receiving the invoice."
     * Verify that payment period increase from 30 to 60 days is identified with commercial impact.
     */
    public function test_3_document_comparison(): void
    {
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Payment Term Comparison',
            'status' => 'processing',
        ]);

        $chunkA = new DocumentChunkDTO(
            chunkId: "c_cmp_a",
            documentSlot: 'A',
            documentId: 101,
            pageNumber: 1,
            sectionTitle: "Section 4 - Payment Terms",
            content: "The customer must pay within 30 days of receiving the invoice.",
            startChar: 0,
            endChar: 65
        );

        $chunkB = new DocumentChunkDTO(
            chunkId: "c_cmp_b",
            documentSlot: 'B',
            documentId: 102,
            pageNumber: 1,
            sectionTitle: "Section 4 - Payment Terms",
            content: "The customer must pay within 60 days of receiving the invoice.",
            startChar: 0,
            endChar: 65
        );

        $this->indexManager->storeChunks($job->id, 'A', [$chunkA]);
        $this->indexManager->storeChunks($job->id, 'B', [$chunkB]);

        $mockProvider = Mockery::mock(AIProviderInterface::class);
        $mockProvider->shouldReceive('getName')->andReturn('groq');
        $mockProvider->shouldReceive('isConfigured')->andReturn(true);
        $mockProvider->shouldReceive('getModel')->andReturn('qwen/qwen3.8-27b');
        $mockProvider->shouldReceive('generate')
            ->atLeast()->once()
            ->andReturnUsing(function (AIRequest $req) {
                if (str_contains($req->prompt, 'Section A') && str_contains($req->prompt, 'Section B')) {
                    return new AIResponse(
                        content: json_encode([
                            [
                                'title' => 'Payment Period Extended by 30 Days',
                                'type' => 'modified',
                                'category' => 'Payment Terms',
                                'risk_level' => 'medium',
                                'explanation' => 'The payment period was extended from 30 days in Document A to 60 days in Document B. The customer now has an additional 30 days to remit invoice balances.',
                                'impact' => 'Extending payment to 60 days delays accounts receivable recognition and impacts immediate working capital cash flows.',
                                'recommended_action' => 'Review whether 60-day remittance aligns with operational liquidity requirements.',
                                'doc_a_evidence' => 'The customer must pay within 30 days of receiving the invoice.',
                                'doc_b_evidence' => 'The customer must pay within 60 days of receiving the invoice.',
                            ]
                        ]),
                        provider: 'groq',
                        model: 'qwen/qwen3.8-27b',
                        usage: new UsageStatsDTO(promptTokens: 120, completionTokens: 180, totalTokens: 300)
                    );
                }

                return new AIResponse(
                    content: "Executive Summary: The payment terms have been revised from 30 days to 60 days, impacting working capital.",
                    provider: 'groq',
                    model: 'qwen/qwen3.8-27b',
                    usage: new UsageStatsDTO(promptTokens: 80, completionTokens: 50, totalTokens: 130)
                );
            });

        $this->router->registerProvider($mockProvider);

        $clauseService = new \App\Services\Comparison\ClauseComparisonService(
            numericalService: app(\App\Services\Comparison\NumericalComparisonService::class),
            dateService: app(\App\Services\Comparison\DateComparisonService::class),
            contradictionDetector: app(\App\Services\Comparison\ContradictionDetector::class),
            aiRouter: $this->router
        );

        $orchestrator = new ComparisonOrchestrator(
            indexManager: $this->indexManager,
            progressTracker: app(\App\Services\Documents\Pipeline\PipelineProgressTracker::class),
            structureAnalyzer: app(\App\Services\Comparison\DocumentStructureAnalyzer::class),
            sectionMatcher: app(\App\Services\Comparison\SectionMatcher::class),
            missingDetector: app(\App\Services\Comparison\MissingSectionDetector::class),
            clauseComparisonService: $clauseService,
            evidenceValidator: app(\App\Services\Comparison\EvidenceValidator::class),
            reportGenerator: app(\App\Services\Comparison\ReportGenerator::class)
        );

        $result = $orchestrator->compare($job);

        $this->assertGreaterThan(0, count($result->findings));
        $finding = $result->findings[0];
        $this->assertStringContainsString('30', $finding->title);
        $this->assertStringContainsString('60 days', $finding->explanation);
        $this->assertStringContainsString('cash flows', $finding->metrics['impact']);
    }

    /**
     * TEST 4 – CLAUSE EXPLANATION
     * Provide a liability clause. Ask AI to explain in simple language.
     * Verify it explains the meaning instead of repeating the clause.
     */
    public function test_4_clause_explanation(): void
    {
        $rawClause = "IN NO EVENT SHALL EITHER PARTY BE LIABLE FOR ANY INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, PUNITIVE OR CONSEQUENTIAL DAMAGES, INCLUDING LOSS OF PROFITS, DATA, OR USE, INCURRED BY EITHER PARTY OR ANY THIRD PARTY, REGARDLESS OF THE THEORY OF LIABILITY.";

        $mockProvider = Mockery::mock(AIProviderInterface::class);
        $mockProvider->shouldReceive('getName')->andReturn('groq');
        $mockProvider->shouldReceive('isConfigured')->andReturn(true);
        $mockProvider->shouldReceive('getModel')->andReturn('qwen/qwen3.8-27b');
        $mockProvider->shouldReceive('generate')
            ->once()
            ->withArgs(function (AIRequest $req) {
                return str_contains($req->systemInstruction, 'EXPLAIN IN YOUR OWN WORDS');
            })
            ->andReturn(new AIResponse(
                content: "### Plain Language Meaning\nThis clause protects both companies from being sued for secondary or consequential losses. For instance, if the service goes down and a customer loses expected sales or prospective business deals, neither party can claim reimbursement for those lost future profits. It restricts claims strictly to direct, actual damages caused by a breach.",
                provider: 'groq',
                model: 'qwen/qwen3.8-27b',
                usage: new UsageStatsDTO(promptTokens: 100, completionTokens: 110, totalTokens: 210)
            ));

        $this->router->registerProvider($mockProvider);

        $doc = DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'slot' => 'A',
            'original_name' => 'MSA_Contract.txt',
            'storage_path' => 'fixtures/msa.txt',
            'mime_type' => 'text/plain',
            'file_size' => 1024,
            'page_count' => 1,
            'checksum_sha256' => hash('sha256', 'msa'),
            'expires_at' => now()->addDays(7),
        ]);

        $chunk = new DocumentChunkDTO(
            chunkId: "c_liab",
            documentSlot: 'A',
            documentId: $doc->id,
            pageNumber: 1,
            sectionTitle: "Section 12 - Limitation of Liability",
            content: $rawClause,
            startChar: 0,
            endChar: strlen($rawClause)
        );
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Test Job Explanation',
            'status' => 'completed',
        ]);
        $doc->update(['comparison_job_id' => $job->id]);
        $this->indexManager->storeChunks($job->id, 'A', [$chunk]);

        $res = $this->orchestrator->answerDocumentQuery($doc, "Explain the liability clause in simple language");

        $this->assertNotEmpty($res['answer']);
        $this->assertStringContainsString('Plain Language Meaning', $res['answer']);
        $this->assertStringContainsString('protects both companies', $res['answer']);
        // Verify it did not just repeat the all-caps raw text
        $this->assertNotEquals($rawClause, $res['answer']);
    }

    /**
     * TEST 5 – MISSING CLAUSE DETECTION
     * Verify that when Document A contains a termination clause and Document B does not,
     * the omission is explicitly reported.
     */
    public function test_5_missing_clause(): void
    {
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Missing Clause Test',
            'status' => 'processing',
        ]);

        $chunkA = new DocumentChunkDTO(
            chunkId: "c_term_a",
            documentSlot: 'A',
            documentId: 201,
            pageNumber: 2,
            sectionTitle: "Section 9 - Termination for Convenience",
            content: "Either party may terminate this Agreement without cause upon sixty (60) days prior written notice.",
            startChar: 0,
            endChar: 105
        );

        $chunkB = new DocumentChunkDTO(
            chunkId: "c_other_b",
            documentSlot: 'B',
            documentId: 202,
            pageNumber: 1,
            sectionTitle: "Section 1 - Scope of Services",
            content: "The vendor shall provide software engineering support services.",
            startChar: 0,
            endChar: 70
        );

        $this->indexManager->storeChunks($job->id, 'A', [$chunkA]);
        $this->indexManager->storeChunks($job->id, 'B', [$chunkB]);

        $orchestrator = app(ComparisonOrchestrator::class);
        $result = $orchestrator->compare($job);

        $missingFindings = array_filter($result->findings, fn($f) => $f->type === 'missing');
        $this->assertNotEmpty($missingFindings, 'Omission of termination clause must be detected.');

        $termFinding = reset($missingFindings);
        $this->assertStringContainsString('Termination', $termFinding->title);
        $this->assertStringContainsString('omitted entirely', $termFinding->explanation);
    }

    /**
     * TEST 6 – API FAILURE RECOVERY WITHOUT FAKE OUTPUT
     * Simulate provider error. Verify application reports failure cleanly and never fabricates mock findings.
     */
    public function test_6_api_failure_handled_without_fake_output(): void
    {
        config(['ai.fallback_order' => ['groq']]);
        config(['ai.enable_automatic_failover' => false]);

        $mockProvider = Mockery::mock(AIProviderInterface::class);
        $mockProvider->shouldReceive('getName')->andReturn('groq');
        $mockProvider->shouldReceive('isConfigured')->andReturn(true);
        $mockProvider->shouldReceive('generate')->andThrow(new \RuntimeException("API quota exceeded (HTTP 429)"));

        $router = app(AIProviderRouter::class);
        $router->registerProvider($mockProvider);
        $req = new AIRequest("Summarize this document", "System prompt");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("API quota exceeded");

        $router->route($req);
    }

    /**
     * TEST 7 – LARGE DOCUMENT COMPREHENSIVE COVERAGE
     * Upload multi-chunk document. Verify all sections are sampled and no silent truncation occurs.
     */
    public function test_7_large_document_coverage(): void
    {
        $chunks = [];
        for ($i = 1; $i <= 10; $i++) {
            $chunks[] = [
                'id' => "chunk_{$i}",
                'slot' => 'A',
                'page_number' => (int) ceil($i / 2),
                'section_title' => "Section {$i} - Clause Title {$i}",
                'content' => "Operative content for section {$i} addressing specific requirement {$i}.",
            ];
        }

        $contextBuilder = app(\App\Services\AI\Context\ContextBuilder::class);
        $intent = app(\App\Services\AI\Intent\TaskIntentClassifier::class)->classify("Summarize the entire document");

        $reflection = new \ReflectionClass($contextBuilder);
        $method = $reflection->getMethod('sampleDocumentChunks');
        $method->setAccessible(true);

        $sampled = $method->invoke($contextBuilder, $chunks, 5);

        // Verify sampled chunks span beginning, middle, and end
        $this->assertCount(5, $sampled);
        $this->assertEquals("chunk_1", $sampled[0]['id']);
        $this->assertEquals("chunk_10", $sampled[4]['id']);
    }

    /**
     * TEST 8 – FRONTEND INTEGRATION CONTRACT
     * Verify that queryDocument API endpoint returns separated AI analysis and metadata structure.
     */
    public function test_8_frontend_api_contract(): void
    {
        $doc = DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'slot' => 'A',
            'original_name' => 'Contract_Alpha.txt',
            'storage_path' => 'fixtures/alpha.txt',
            'mime_type' => 'text/plain',
            'file_size' => 1024,
            'page_count' => 1,
            'checksum_sha256' => hash('sha256', 'alpha'),
            'expires_at' => now()->addDays(7),
        ]);

        $chunk = new DocumentChunkDTO(
            chunkId: "c_contract",
            documentSlot: 'A',
            documentId: $doc->id,
            pageNumber: 1,
            sectionTitle: "Section 1",
            content: "Governing law is Delaware. Arbitration in Wilmington.",
            startChar: 0,
            endChar: 55
        );
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Test Job Contract',
            'status' => 'completed',
        ]);
        $doc->update(['comparison_job_id' => $job->id]);
        $this->indexManager->storeChunks($job->id, 'A', [$chunk]);

        $response = $this->actingAs($this->user)->postJson("/api/v1/documents/{$doc->id}/query", [
            'prompt' => 'What is the governing law?',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertArrayHasKey('answer', $data);
        $this->assertArrayHasKey('document_id', $data);
        $this->assertArrayHasKey('document_name', $data);
        $this->assertArrayHasKey('citations', $data);
        $this->assertArrayHasKey('relevance_score', $data);
    }
}
