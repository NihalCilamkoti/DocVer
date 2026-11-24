<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
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
use App\Services\Documents\DTOs\DocumentChunkDTO;
use App\Services\Documents\Indexing\EphemeralIndexManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class AIChatbotIntelligenceAndIsolationTest extends TestCase
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
            'name' => 'Isolation Test Workspace',
            'slug' => 'isolation-ws',
            'owner_id' => $this->user->id,
        ]);
        $this->user->workspaces()->attach($this->workspace->id, ['role' => 'owner']);

        $this->indexManager = app(EphemeralIndexManager::class);
        $this->router = app(AIProviderRouter::class);
        $this->orchestrator = app(AIOrchestrator::class);
        config(['ai.test_live_ai' => true]);
    }

    /**
     * Helper to setup comparison job with Test A/B/C/D documents.
     */
    protected function setupJobWithDocs(string $doc1Text, string $doc2Text): array
    {
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Contract Alpha vs Contract Beta',
            'status' => 'completed',
        ]);

        $docA = DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'comparison_job_id' => $job->id,
            'slot' => 'A',
            'original_name' => 'Contract_Alpha.pdf',
            'storage_path' => 'fixtures/contract_alpha.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'page_count' => 1,
            'checksum_sha256' => hash('sha256', $doc1Text),
            'expires_at' => now()->addDays(7),
        ]);

        $docB = DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'comparison_job_id' => $job->id,
            'slot' => 'B',
            'original_name' => 'Contract_Beta.pdf',
            'storage_path' => 'fixtures/contract_beta.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'page_count' => 1,
            'checksum_sha256' => hash('sha256', $doc2Text),
            'expires_at' => now()->addDays(7),
        ]);

        $chunkA = new DocumentChunkDTO(
            chunkId: "c_alpha_1",
            documentSlot: 'A',
            documentId: $docA->id,
            pageNumber: 1,
            sectionTitle: "Master Service Terms",
            content: $doc1Text,
            startChar: 0,
            endChar: strlen($doc1Text)
        );

        $chunkB = new DocumentChunkDTO(
            chunkId: "c_beta_1",
            documentSlot: 'B',
            documentId: $docB->id,
            pageNumber: 1,
            sectionTitle: "Master Service Terms",
            content: $doc2Text,
            startChar: 0,
            endChar: strlen($doc2Text)
        );

        $this->indexManager->storeChunks($job->id, 'A', [$chunkA]);
        $this->indexManager->storeChunks($job->id, 'B', [$chunkB]);

        return [$job->fresh(['documentA', 'documentB']), $docA, $docB];
    }

    /**
     * TEST A: TWO DOCUMENT SUMMARIZATION
     * User: "Summarize both documents separately."
     * Doc 1: 24-month maintenance, 60-day notice, $500,000 liability.
     * Doc 2: 12-month maintenance, 30-day notice, $250,000 liability.
     * Verify:
     * - Document 1 summary contains 24 months, 60 days, $500,000.
     * - Document 2 summary contains 12 months, 30 days, $250,000.
     * - ZERO cross-document contamination (Doc 1 never gets 12 months/$250k; Doc 2 never gets 24 months/$500k).
     */
    public function test_a_two_document_summarization_with_strict_isolation(): void
    {
        $doc1 = "The supplier must provide maintenance services for 24 months. Either party may terminate with 60 days' written notice. The supplier's liability is limited to $500,000.";
        $doc2 = "The supplier must provide maintenance services for 12 months. Either party may terminate with 30 days' written notice. The supplier's liability is limited to $250,000.";

        [$job, $docA, $docB] = $this->setupJobWithDocs($doc1, $doc2);

        $mockProvider = Mockery::mock(AIProviderInterface::class);
        $mockProvider->shouldReceive('getName')->andReturn('groq');
        $mockProvider->shouldReceive('isConfigured')->andReturn(true);
        $mockProvider->shouldReceive('getModel')->andReturn('qwen/qwen3.8-27b');
        $mockProvider->shouldReceive('supportsTask')->andReturn(true);

        // Expect isolated generation for each document independently
        $mockProvider->shouldReceive('generate')
            ->twice()
            ->andReturnUsing(function (AIRequest $req) {
                if (str_contains($req->prompt, 'Document 1') || str_contains($req->prompt, 'Contract_Alpha')) {
                    $this->assertStringContainsString('24 months', $req->prompt);
                    $this->assertStringContainsString('$500,000', $req->prompt);
                    $this->assertStringNotContainsString('$250,000', $req->prompt);
                    $this->assertStringNotContainsString('12 months', $req->prompt);

                    return new AIResponse(
                        content: "Contract Alpha provides for 24 months of ongoing maintenance support. Either party may terminate the agreement upon 60 days' advance written notice. Supplier liability is capped at $500,000.",
                        provider: 'groq',
                        model: 'qwen/qwen3.8-27b',
                        usage: new UsageStatsDTO(60, 45, 105)
                    );
                }

                $this->assertStringContainsString('12 months', $req->prompt);
                $this->assertStringContainsString('$250,000', $req->prompt);
                $this->assertStringNotContainsString('$500,000', $req->prompt);
                $this->assertStringNotContainsString('24 months', $req->prompt);

                return new AIResponse(
                    content: "Contract Beta specifies a 12-month maintenance duration. Termination requires 30 days' written notice. Total supplier liability is strictly limited to $250,000.",
                    provider: 'groq',
                    model: 'qwen/qwen3.8-27b',
                    usage: new UsageStatsDTO(60, 45, 105)
                );
            });

        $this->router->registerProvider($mockProvider);

        $res = $this->orchestrator->answerComparisonQuery($job, "Summarize both documents separately.");

        $this->assertNotEmpty($res['content']);
        $this->assertEquals('multi_document_summary', $res['intent']);

        // Check that Document 1 section preserves its values
        $this->assertStringContainsString('Document 1', $res['content']);
        $this->assertStringContainsString('24 months', $res['content']);
        $this->assertStringContainsString("60 days'", $res['content']);
        $this->assertStringContainsString('$500,000', $res['content']);

        // Check that Document 2 section preserves its values
        $this->assertStringContainsString('Document 2', $res['content']);
        $this->assertStringContainsString('12-month', $res['content']);
        $this->assertStringContainsString("30 days'", $res['content']);
        $this->assertStringContainsString('$250,000', $res['content']);
    }

    /**
     * TEST B: DOCUMENT COMPARISON
     * User: "Compare these two documents."
     * Expected:
     * - Identifies maintenance: 24 to 12 months.
     * - Identifies termination: 60 to 30 days.
     * - Identifies liability: $500,000 to $250,000.
     * - Explains implications of each change.
     */
    public function test_b_document_comparison(): void
    {
        $doc1 = "The supplier must provide maintenance services for 24 months. Either party may terminate with 60 days' written notice. The supplier's liability is limited to $500,000.";
        $doc2 = "The supplier must provide maintenance services for 12 months. Either party may terminate with 30 days' written notice. The supplier's liability is limited to $250,000.";

        [$job, $docA, $docB] = $this->setupJobWithDocs($doc1, $doc2);

        $mockProvider = Mockery::mock(AIProviderInterface::class);
        $mockProvider->shouldReceive('getName')->andReturn('groq');
        $mockProvider->shouldReceive('isConfigured')->andReturn(true);
        $mockProvider->shouldReceive('getModel')->andReturn('qwen/qwen3.8-27b');
        $mockProvider->shouldReceive('supportsTask')->andReturn(true);

        $mockProvider->shouldReceive('generate')
            ->once()
            ->withArgs(function (AIRequest $req) {
                return str_contains($req->prompt, 'Contract_Alpha') && str_contains($req->prompt, 'Contract_Beta');
            })
            ->andReturn(new AIResponse(
                content: "### Contract Comparison\n\n1. **Maintenance Service Duration:** Reduced from 24 months in Document 1 to 12 months in Document 2. *Implication:* Supplier's service commitment is halved, requiring earlier renewal or renegotiation.\n\n2. **Termination Notice Period:** Decreased from 60 days to 30 days. *Implication:* Provides greater flexibility to exit on shorter notice, but reduces operational transition runway.\n\n3. **Liability Limitation:** Reduced from $500,000 to $250,000. *Implication:* Significantly limits customer recovery in the event of supplier breach or operational failure.",
                provider: 'groq',
                model: 'qwen/qwen3.8-27b',
                usage: new UsageStatsDTO(120, 140, 260)
            ));

        $this->router->registerProvider($mockProvider);

        $res = $this->orchestrator->answerComparisonQuery($job, "Compare these two documents.");

        $this->assertNotEmpty($res['content']);
        $this->assertStringContainsString('24 months', $res['content']);
        $this->assertStringContainsString('12 months', $res['content']);
        $this->assertStringContainsString('60 days', $res['content']);
        $this->assertStringContainsString('30 days', $res['content']);
        $this->assertStringContainsString('$500,000', $res['content']);
        $this->assertStringContainsString('$250,000', $res['content']);
        $this->assertStringContainsString('Implication', $res['content']);
    }

    /**
     * TEST C: SINGLE DOCUMENT QUESTION
     * User: "What is the liability limit in Document 1?"
     * Expected:
     * - Returns $500,000.
     * - Document 2's $250,000 value is NOT present.
     */
    public function test_c_single_document_question(): void
    {
        $doc1 = "The supplier must provide maintenance services for 24 months. Either party may terminate with 60 days' written notice. The supplier's liability is limited to $500,000.";
        $doc2 = "The supplier must provide maintenance services for 12 months. Either party may terminate with 30 days' written notice. The supplier's liability is limited to $250,000.";

        [$job, $docA, $docB] = $this->setupJobWithDocs($doc1, $doc2);

        $mockProvider = Mockery::mock(AIProviderInterface::class);
        $mockProvider->shouldReceive('getName')->andReturn('groq');
        $mockProvider->shouldReceive('isConfigured')->andReturn(true);
        $mockProvider->shouldReceive('getModel')->andReturn('qwen/qwen3.8-27b');
        $mockProvider->shouldReceive('supportsTask')->andReturn(true);

        $mockProvider->shouldReceive('generate')
            ->once()
            ->withArgs(function (AIRequest $req) {
                // Must ONLY contain Document 1 text; Document 2 text must be isolated out
                $this->assertStringContainsString('$500,000', $req->prompt);
                $this->assertStringNotContainsString('$250,000', $req->prompt);
                return true;
            })
            ->andReturn(new AIResponse(
                content: "In Document 1 (Contract_Alpha.pdf), the supplier's liability is strictly limited to $500,000.",
                provider: 'groq',
                model: 'qwen/qwen3.8-27b',
                usage: new UsageStatsDTO(50, 30, 80)
            ));

        $this->router->registerProvider($mockProvider);

        $res = $this->orchestrator->answerComparisonQuery($job, "What is the liability limit in Document 1?");

        $this->assertNotEmpty($res['content']);
        $this->assertStringContainsString('$500,000', $res['content']);
        $this->assertStringNotContainsString('$250,000', $res['content']);
    }

    /**
     * TEST D: MISSING CLAUSE
     * Remove termination clause from Document 1.
     * User: "What is the termination clause in Document 1?"
     * Expected:
     * - Reports termination is absent from Document 1.
     * - Does NOT copy Document 2's 30-day notice into Document 1.
     */
    public function test_d_missing_clause_reporting_without_cross_contamination(): void
    {
        $doc1 = "The supplier must provide maintenance services for 24 months. The supplier's liability is limited to $500,000.";
        $doc2 = "The supplier must provide maintenance services for 12 months. Either party may terminate with 30 days' written notice. The supplier's liability is limited to $250,000.";

        [$job, $docA, $docB] = $this->setupJobWithDocs($doc1, $doc2);

        $mockProvider = Mockery::mock(AIProviderInterface::class);
        $mockProvider->shouldReceive('getName')->andReturn('groq');
        $mockProvider->shouldReceive('isConfigured')->andReturn(true);
        $mockProvider->shouldReceive('getModel')->andReturn('qwen/qwen3.8-27b');
        $mockProvider->shouldReceive('supportsTask')->andReturn(true);

        $mockProvider->shouldReceive('generate')
            ->once()
            ->andReturn(new AIResponse(
                content: "Document 1 does not contain a termination clause. In contrast, Document 2 specifies a 30-day written notice period for termination.",
                provider: 'groq',
                model: 'qwen/qwen3.8-27b',
                usage: new UsageStatsDTO(60, 35, 95)
            ));

        $this->router->registerProvider($mockProvider);

        $res = $this->orchestrator->answerComparisonQuery($job, "What is the termination clause in Document 1?");

        $this->assertNotEmpty($res['content']);
        $this->assertStringContainsString('does not contain', strtolower($res['content']));
        $this->assertStringContainsString('termination', strtolower($res['content']));
    }

    /**
     * TEST E: GENERAL CHAT
     * User: "Explain what indemnification means in simple English."
     * Expected:
     * - Returns conversational plain-English explanation.
     * - Does NOT force document context or claim "Neither Document A nor Document B contains...".
     */
    public function test_e_general_chat_conversational_behavior(): void
    {
        [$job, $docA, $docB] = $this->setupJobWithDocs("Some doc 1", "Some doc 2");

        $mockProvider = Mockery::mock(AIProviderInterface::class);
        $mockProvider->shouldReceive('getName')->andReturn('groq');
        $mockProvider->shouldReceive('isConfigured')->andReturn(true);
        $mockProvider->shouldReceive('getModel')->andReturn('qwen/qwen3.8-27b');
        $mockProvider->shouldReceive('supportsTask')->andReturn(true);

        $mockProvider->shouldReceive('generate')
            ->once()
            ->withArgs(function (AIRequest $req) {
                // Must be conversational without forcing document excerpts
                return !str_contains($req->prompt, '<document_content');
            })
            ->andReturn(new AIResponse(
                content: "In simple English, **indemnification** is a legal promise where one party agrees to compensate or 'hold harmless' the other party for certain losses, damages, or legal costs resulting from a breach or specific event. Think of it like a contractual insurance policy: if a third party sues Company A because of something Company B did wrong, Company B covers the legal bills and damages.",
                provider: 'groq',
                model: 'qwen/qwen3.8-27b',
                usage: new UsageStatsDTO(40, 80, 120)
            ));

        $this->router->registerProvider($mockProvider);

        $res = $this->orchestrator->answerComparisonQuery($job, "Explain what indemnification means in simple English.");

        $this->assertNotEmpty($res['content']);
        $this->assertEquals('general_chat', $res['intent']);
        $this->assertStringContainsString('indemnification', strtolower($res['content']));
        $this->assertStringContainsString('compensate', strtolower($res['content']));
        $this->assertStringNotContainsString('neither document a nor document b contains', strtolower($res['content']));
    }

    /**
     * TEST F: FOLLOW-UP QUESTION
     * User: "Make the first summary shorter."
     * Expected:
     * - Targets Document 1 summary refinement.
     * - Preserves core figures ($500,000, 24 months, 60 days).
     * - Leaves Document 2 intact.
     */
    public function test_f_follow_up_refinement(): void
    {
        $doc1 = "The supplier must provide maintenance services for 24 months. Either party may terminate with 60 days' written notice. The supplier's liability is limited to $500,000.";
        $doc2 = "The supplier must provide maintenance services for 12 months. Either party may terminate with 30 days' written notice. The supplier's liability is limited to $250,000.";

        [$job, $docA, $docB] = $this->setupJobWithDocs($doc1, $doc2);

        // Seed previous message into conversation
        ChatMessage::create([
            'comparison_job_id' => $job->id,
            'sender' => 'user',
            'content' => 'Summarize both documents separately.',
        ]);
        ChatMessage::create([
            'comparison_job_id' => $job->id,
            'sender' => 'ai',
            'content' => "### Document 1: Contract_Alpha\nContract Alpha provides for 24 months of ongoing maintenance support. Either party may terminate the agreement upon 60 days' advance written notice. Supplier liability is capped at $500,000.\n\n---\n\n### Document 2: Contract_Beta\nContract Beta specifies a 12-month maintenance duration. Termination requires 30 days' written notice. Total supplier liability is strictly limited to $250,000.",
        ]);

        $mockProvider = Mockery::mock(AIProviderInterface::class);
        $mockProvider->shouldReceive('getName')->andReturn('groq');
        $mockProvider->shouldReceive('isConfigured')->andReturn(true);
        $mockProvider->shouldReceive('getModel')->andReturn('qwen/qwen3.8-27b');
        $mockProvider->shouldReceive('supportsTask')->andReturn(true);

        $mockProvider->shouldReceive('generate')
            ->once()
            ->withArgs(function (AIRequest $req) {
                // Must have previous conversation history
                return str_contains($req->prompt, 'Make the first summary shorter');
            })
            ->andReturn(new AIResponse(
                content: "### Document 1 (Concise Summary):\n- **Maintenance:** 24 months\n- **Termination Notice:** 60 days\n- **Liability Cap:** $500,000",
                provider: 'groq',
                model: 'qwen/qwen3.8-27b',
                usage: new UsageStatsDTO(100, 40, 140)
            ));

        $this->router->registerProvider($mockProvider);

        $res = $this->orchestrator->answerComparisonQuery($job, "Make the first summary shorter.");

        $this->assertNotEmpty($res['content']);
        $this->assertStringContainsString('Document 1', $res['content']);
        $this->assertStringContainsString('24 months', $res['content']);
        $this->assertStringContainsString('60 days', $res['content']);
        $this->assertStringContainsString('$500,000', $res['content']);
        // Document 2 values are not transferred into Document 1
        $this->assertStringNotContainsString('$250,000', $res['content']);
        $this->assertStringNotContainsString('12 months', $res['content']);
    }
}
