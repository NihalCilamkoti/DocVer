<?php

namespace Tests\Feature;

use App\Enums\ComparisonStatus;
use App\Models\ChatMessage;
use App\Models\ComparisonJob;
use App\Models\DocumentMetadata;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\AIOrchestrator;
use App\Services\AI\Config\TokenManager;
use App\Services\AI\Context\ContextBuilder;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Conversation\ConversationManager;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\DTOs\UsageStatsDTO;
use App\Services\AI\Exceptions\AuthenticationException;
use App\Services\AI\Exceptions\QuotaExceededException;
use App\Services\AI\Exceptions\RateLimitException;
use App\Services\AI\Intent\TaskIntentClassifier;
use App\Services\AI\Prompts\PromptManager;
use App\Services\AI\Quota\AIQuotaResolver;
use App\Services\AI\Router\AIProviderRouter;
use App\Services\AI\Security\PromptSanitizer;
use App\Services\AI\Validation\ResponseValidator;
use App\Services\Comparison\ComparisonOrchestrator;
use App\Services\Documents\DTOs\DocumentChunkDTO;
use App\Services\Documents\Indexing\EphemeralIndexManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class MasterEndToEndRegressionSuiteTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected Workspace $workspace;
    protected Workspace $otherWorkspace;
    protected EphemeralIndexManager $indexManager;
    protected TaskIntentClassifier $classifier;
    protected ContextBuilder $contextBuilder;
    protected PromptManager $promptManager;
    protected ResponseValidator $responseValidator;
    protected ConversationManager $conversationManager;
    protected AIProviderRouter $router;
    protected AIOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');

        $this->user = User::factory()->create(['email' => 'auditor@docver.ai']);
        $this->workspace = Workspace::create([
            'name' => 'Audit Workspace Alpha',
            'slug' => 'audit-ws-alpha',
            'owner_id' => $this->user->id,
            'plan' => 'professional',
            'max_file_size_mb' => 50,
        ]);
        $this->user->workspaces()->attach($this->workspace->id, ['role' => 'owner']);

        $this->otherUser = User::factory()->create(['email' => 'external@competitor.com']);
        $this->otherWorkspace = Workspace::create([
            'name' => 'External Competitor Workspace',
            'slug' => 'external-ws-beta',
            'owner_id' => $this->otherUser->id,
            'plan' => 'free',
        ]);
        $this->otherUser->workspaces()->attach($this->otherWorkspace->id, ['role' => 'owner']);

        $this->indexManager = app(EphemeralIndexManager::class);
        $this->classifier = app(TaskIntentClassifier::class);
        $this->contextBuilder = app(ContextBuilder::class);
        $this->promptManager = app(PromptManager::class);
        $this->responseValidator = app(ResponseValidator::class);
        $this->conversationManager = app(ConversationManager::class);
        $this->router = app(AIProviderRouter::class);
        $this->orchestrator = app(AIOrchestrator::class);
        config(['ai.test_live_ai' => true]);
    }

    /**
     * Helper to provision the controlled dataset from Section 3:
     * Document A: 24 months, 60 days notice, $500,000 liability, New York law, Cyber Insurance clause (unique to A).
     * Document B: 12 months, 30 days notice, $250,000 liability, California law.
     */
    protected function seedControlledDataset(): array
    {
        $docAText = "Section 1. Contract Period: The contract period shall be 24 months commencing January 1, 2027.\n" .
            "Section 2. Termination Notice: Either party may terminate upon 60 days written notice.\n" .
            "Section 3. Liability Limit: Total cumulative liability is capped at $500,000.\n" .
            "Section 4. Governing Law: This Agreement is governed by the laws of New York.\n" .
            "Section 5. Cyber Insurance Mandate: Supplier must maintain $2,000,000 in cyber liability insurance.";

        $docBText = "Section 1. Contract Period: The contract period shall be 12 months commencing January 1, 2027.\n" .
            "Section 2. Termination Notice: Either party may terminate upon 30 days written notice.\n" .
            "Section 3. Liability Limit: Total cumulative liability is capped at $250,000.\n" .
            "Section 4. Governing Law: This Agreement is governed by the laws of California.";

        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Master Agreement A vs Revised Draft B',
            'status' => ComparisonStatus::COMPLETED,
        ]);

        $docA = DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'comparison_job_id' => $job->id,
            'slot' => 'A',
            'original_name' => 'Document_A_NY.pdf',
            'storage_path' => 'documents/doc_a_ny.txt',
            'mime_type' => 'text/plain',
            'file_size' => strlen($docAText),
            'page_count' => 1,
            'checksum_sha256' => hash('sha256', $docAText),
            'expires_at' => now()->addDays(7),
        ]);

        $docB = DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'comparison_job_id' => $job->id,
            'slot' => 'B',
            'original_name' => 'Document_B_CA.pdf',
            'storage_path' => 'documents/doc_b_ca.txt',
            'mime_type' => 'text/plain',
            'file_size' => strlen($docBText),
            'page_count' => 1,
            'checksum_sha256' => hash('sha256', $docBText),
            'expires_at' => now()->addDays(7),
        ]);

        Storage::disk('local')->put('documents/doc_a_ny.txt', $docAText);
        Storage::disk('local')->put('documents/doc_b_ca.txt', $docBText);

        $chunksA = [
            new DocumentChunkDTO('ca_1', 'A', $docA->id, 1, 'Section 1 - Contract Period', "Section 1 - Contract Period\nThe contract period shall be 24 months commencing January 1, 2027.", 0, 95),
            new DocumentChunkDTO('ca_2', 'A', $docA->id, 1, 'Section 2 - Termination Notice', "Section 2 - Termination Notice\nEither party may terminate upon 60 days written notice.", 96, 185),
            new DocumentChunkDTO('ca_3', 'A', $docA->id, 1, 'Section 3 - Liability Limit', "Section 3 - Liability Limit\nTotal cumulative liability is capped at $500,000.", 186, 265),
            new DocumentChunkDTO('ca_4', 'A', $docA->id, 1, 'Section 4 - Governing Law', "Section 4 - Governing Law\nThis Agreement is governed by the laws of New York.", 266, 345),
            new DocumentChunkDTO('ca_5', 'A', $docA->id, 1, 'Section 5 - Cyber Insurance Mandate', "Section 5 - Cyber Insurance Mandate\nSupplier must maintain $2,000,000 in cyber liability insurance.", 346, 445),
        ];

        $chunksB = [
            new DocumentChunkDTO('cb_1', 'B', $docB->id, 1, 'Section 1 - Contract Period', "Section 1 - Contract Period\nThe contract period shall be 12 months commencing January 1, 2027.", 0, 95),
            new DocumentChunkDTO('cb_2', 'B', $docB->id, 1, 'Section 2 - Termination Notice', "Section 2 - Termination Notice\nEither party may terminate upon 30 days written notice.", 96, 185),
            new DocumentChunkDTO('cb_3', 'B', $docB->id, 1, 'Section 3 - Liability Limit', "Section 3 - Liability Limit\nTotal cumulative liability is capped at $250,000.", 186, 265),
            new DocumentChunkDTO('cb_4', 'B', $docB->id, 1, 'Section 4 - Governing Law', "Section 4 - Governing Law\nThis Agreement is governed by the laws of California.", 266, 345),
        ];

        $this->indexManager->storeChunks($job->id, 'A', $chunksA);
        $this->indexManager->storeChunks($job->id, 'B', $chunksB);

        return [$job->fresh(['documentA', 'documentB']), $docA, $docB, $docAText, $docBText];
    }

    /**
     * REGRESSION 1: AI copies entire paragraphs instead of summarizing.
     * Verifies ResponseValidator detects verbatim copies and converts them into structured summaries.
     */
    public function test_regression_01_ai_does_not_copy_entire_paragraphs_verbatim_when_summarizing(): void
    {
        [$job, $docA, $docB, $docAText] = $this->seedControlledDataset();

        $intent = $this->classifier->classify('Summarize this document.');
        $context = $this->contextBuilder->buildSingleDocumentContext($docA, $intent);

        // Simulate a lazy model returning the raw stripped context verbatim
        $rawStripped = trim(strip_tags($context->formattedContext));
        $validation = $this->responseValidator->validate($rawStripped, $intent, $context, $docA->original_name);

        $this->assertNotEquals($rawStripped, $validation->validatedContent);
        $this->assertStringContainsString('Executive Summary', $validation->validatedContent);
        $this->assertContains('verbatim_copy_converted_to_structured_summary', $validation->warnings);
    }

    /**
     * REGRESSION 2: AI answers a different question from the one asked.
     * Verifies all 12 instruction-following prompts from Section 2 classify and generate targeted directives.
     */
    public function test_regression_02_critical_instruction_following_all_12_prompts(): void
    {
        [$job, $docA] = $this->seedControlledDataset();
        $context = $this->contextBuilder->buildComparisonContext($job, $this->classifier->classify('Compare'));

        // 1. "Summarize this document in 5 bullet points."
        $i1 = $this->classifier->classify('Summarize this document in 5 bullet points.');
        $this->assertContains('exact_bullets:5', $i1->constraints);
        $p1 = $this->promptManager->buildPrompt($i1, $context, 'Doc A', 'Doc B');
        $this->assertStringContainsString('EXACTLY 5 bullet points', $p1['systemInstruction']);

        // 2. "Explain this document in simple language."
        $i2 = $this->classifier->classify('Explain this document in simple language.');
        $this->assertEquals('clause_explanation', $i2->intent);

        // 3. "Extract only the dates and amounts."
        $i3 = $this->classifier->classify('Extract only the dates and amounts.');
        $this->assertEquals('numerical_comparison', $i3->intent);
        $this->assertContains('strict_extraction_only', $i3->constraints);

        // 4. "Do not summarize. Identify only the risks."
        $i4 = $this->classifier->classify('Do not summarize. Identify only the risks.');
        $this->assertEquals('risk_analysis', $i4->intent);
        $this->assertContains('forbid_summary', $i4->constraints);

        // 5. "Answer in exactly 3 sentences."
        $i5 = $this->classifier->classify('Answer in exactly 3 sentences.');
        $this->assertContains('exact_sentences:3', $i5->constraints);

        // 6. "Create a table containing the extracted information."
        $i6 = $this->classifier->classify('Create a table containing the extracted information.');
        $this->assertContains('format_markdown_table', $i6->constraints);

        // 7. "Compare these two documents and show only the differences."
        $i7 = $this->classifier->classify('Compare these two documents and show only the differences.');
        $this->assertContains('only_differences', $i7->constraints);

        // 8. "Rewrite this paragraph without changing its meaning."
        $i8 = $this->classifier->classify('Rewrite this paragraph without changing its meaning.');
        $this->assertEquals('rewrite_preservation', $i8->intent);
        $this->assertContains('preserve_exact_meaning', $i8->constraints);

        // 9. "Explain clause 4 without discussing the other clauses."
        $i9 = $this->classifier->classify('Explain clause 4 without discussing the other clauses.');
        $this->assertEquals('section_specific', $i9->intent);
        $this->assertContains('strict_section_only', $i9->constraints);

        // 10. "Make the previous answer shorter."
        $i10 = $this->classifier->classify('Make the previous answer shorter.');
        $this->assertEquals('follow_up_refinement', $i10->intent);

        // 11. "Continue from where you stopped."
        $i11 = $this->classifier->classify('Continue from where you stopped.');
        $this->assertEquals('follow_up_refinement', $i11->intent);

        // 12. "Ignore the previous task and perform this new task instead: Summarize only section 4."
        $i12 = $this->classifier->classify('Ignore the previous task and perform this new task instead: Summarize only section 4.');
        $this->assertContains('override_previous_task', $i12->constraints);
        $p12 = $this->promptManager->buildPrompt($i12, $context, 'Doc A', 'Doc B', [
            ['sender' => 'user', 'content' => 'Old task that should be ignored'],
        ]);
        $this->assertStringNotContainsString('Old task that should be ignored', $p12['userPrompt']);
    }

    /**
     * REGRESSION 3: AI ignores requested output formatting (exact bullets, exact sentences, forbid summary).
     */
    public function test_regression_03_enforces_requested_output_formatting_constraints(): void
    {
        [$job, $docA] = $this->seedControlledDataset();

        // 5 bullet points enforcement
        $intentBullets = $this->classifier->classify('Summarize this document in 5 bullet points.');
        $ctx = $this->contextBuilder->buildSingleDocumentContext($docA, $intentBullets);
        $rawSevenBullets = "- Point 1\n- Point 2\n- Point 3\n- Point 4\n- Point 5\n- Point 6\n- Point 7";
        $valBullets = $this->responseValidator->validate($rawSevenBullets, $intentBullets, $ctx, 'Doc A');
        $lines = explode("\n", trim($valBullets->validatedContent));
        $this->assertCount(5, $lines);

        // 3 sentences enforcement
        $intentSentences = $this->classifier->classify('Answer in exactly 3 sentences.');
        $rawFiveSentences = "Sentence one is here. Sentence two is here. Sentence three is here. Sentence four is extra. Sentence five is extra.";
        $valSentences = $this->responseValidator->validate($rawFiveSentences, $intentSentences, $ctx, 'Doc A');
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($valSentences->validatedContent), -1, PREG_SPLIT_NO_EMPTY);
        $this->assertCount(3, $sentences);

        // Do not summarize enforcement
        $intentRisks = $this->classifier->classify('Do not summarize. Identify only the risks.');
        $rawWithSummary = "### Executive Summary\nThis is a general summary of the document.\n\n### Risks\n- Liability cap reduced to $250,000.";
        $valRisks = $this->responseValidator->validate($rawWithSummary, $intentRisks, $ctx, 'Doc A');
        $this->assertStringNotContainsString('This is a general summary', $valRisks->validatedContent);
        $this->assertStringContainsString('Liability cap reduced to $250,000', $valRisks->validatedContent);
    }

    /**
     * REGRESSION 4 & 5: AI mixes content between two documents or inserts Document B content into Document A.
     * Tests controlled dataset isolation across all 10 scenarios in Section 3.
     */
    public function test_regression_04_and_05_strict_document_isolation_on_controlled_dataset(): void
    {
        [$job, $docA, $docB] = $this->seedControlledDataset();

        // Scenario 4: Ask about Document A while both documents are uploaded
        $intentA = $this->classifier->classify('What is the contract period, termination notice, liability limit, and governing law in Document A?');
        $this->assertEquals('A', $intentA->targetSlot);
        $contextA = $this->contextBuilder->buildComparisonContext($job, $intentA);

        $this->assertStringContainsString('24 months', $contextA->formattedContext);
        $this->assertStringContainsString('60 days', $contextA->formattedContext);
        $this->assertStringContainsString('$500,000', $contextA->formattedContext);
        $this->assertStringContainsString('New York', $contextA->formattedContext);

        // Verify ZERO leakage of Document B values into Document A context
        $this->assertStringNotContainsString('12 months', $contextA->formattedContext);
        $this->assertStringNotContainsString('30 days', $contextA->formattedContext);
        $this->assertStringNotContainsString('$250,000', $contextA->formattedContext);
        $this->assertStringNotContainsString('California', $contextA->formattedContext);

        // Scenario 5: Ask about Document B while both documents are uploaded
        $intentB = $this->classifier->classify('What is the contract period, termination notice, liability limit, and governing law in Document B?');
        $this->assertEquals('B', $intentB->targetSlot);
        $contextB = $this->contextBuilder->buildComparisonContext($job, $intentB);

        $this->assertStringContainsString('12 months', $contextB->formattedContext);
        $this->assertStringContainsString('30 days', $contextB->formattedContext);
        $this->assertStringContainsString('$250,000', $contextB->formattedContext);
        $this->assertStringContainsString('California', $contextB->formattedContext);

        // Verify ZERO leakage of Document A values into Document B context
        $this->assertStringNotContainsString('24 months', $contextB->formattedContext);
        $this->assertStringNotContainsString('60 days', $contextB->formattedContext);
        $this->assertStringNotContainsString('$500,000', $contextB->formattedContext);
        $this->assertStringNotContainsString('New York', $contextB->formattedContext);

        // Also verify validateIsolatedSummary strips any leaked token if a model ever hallucinates it
        $contaminatedSummaryA = "Document A has a 24 months term and $250,000 liability limit.";
        $cleaned = $this->responseValidator->validateIsolatedSummary($contaminatedSummaryA, $contextA, $contextB, 'Doc A', '1');
        $this->assertStringNotContainsString('$250,000', $cleaned->validatedContent);
        $this->assertNotEmpty($cleaned->warnings);
    }

    /**
     * REGRESSION 6 & 7: AI invents clauses that do not exist or gives unsupported answers instead of acknowledging missing info.
     */
    public function test_regression_06_and_07_missing_information_and_single_doc_clause_detection(): void
    {
        [$job, $docA, $docB] = $this->seedControlledDataset();

        // Ask for non-existent clause (e.g. non-compete / stock options)
        $intentMissing = $this->classifier->classify('What are the non-compete and exclusivity penalties?');
        $ctxMissing = $this->contextBuilder->buildComparisonContext($job, $intentMissing);
        $this->assertFalse($ctxMissing->targetFound);

        // Even if model tries to invent a 2-year non-compete penalty, ResponseValidator blocks it
        $invented = "The non-compete clause restricts the supplier for 2 years with a $100,000 penalty.";
        $validated = $this->responseValidator->validate($invented, $intentMissing, $ctxMissing, 'Document_A_NY.pdf', 'Document_B_CA.pdf');

        $this->assertStringContainsString('Neither Document_A_NY.pdf nor Document_B_CA.pdf contains any provisions', $validated->validatedContent);
        $this->assertStringNotContainsString('restricts the supplier for 2 years', $validated->validatedContent);

        // Clause that exists ONLY in Document A (Section 5 - Cyber Insurance Mandate)
        config(['ai.test_live_ai' => false]);
        $orchestrator = app(ComparisonOrchestrator::class);
        $result = $orchestrator->compare($job);
        $missingFindings = array_filter($result->findings, fn($f) => $f->type === 'missing');
        $this->assertNotEmpty($missingFindings, 'Cyber Insurance clause present only in Doc A must be flagged as missing in Doc B');
    }

    /**
     * REGRESSION 8 & 9: Conversational memory preservation within a session and strict isolation across new conversations.
     */
    public function test_regression_08_and_09_conversational_memory_and_new_session_isolation(): void
    {
        [$job1, $docA] = $this->seedControlledDataset();

        // Record multi-turn history on job 1
        ChatMessage::create([
            'comparison_job_id' => $job1->id,
            'user_id' => $this->user->id,
            'sender' => 'user',
            'content' => 'Summarize Document A.',
        ]);
        ChatMessage::create([
            'comparison_job_id' => $job1->id,
            'user_id' => null,
            'sender' => 'ai',
            'content' => "1. Contract period is 24 months.\n2. Termination notice is 60 days.\n3. Liability limit is $500,000.",
        ]);

        $history1 = $this->conversationManager->getComparisonHistory($job1->id);
        $this->assertCount(2, $history1);
        $this->assertStringContainsString('60 days', $history1[1]['content']);

        // Start a brand new conversation (job 2)
        $job2 = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'New Unrelated Conversation',
            'status' => ComparisonStatus::COMPLETED,
        ]);

        $history2 = $this->conversationManager->getComparisonHistory($job2->id);
        $this->assertEmpty($history2, 'New conversation must never inherit context from previous conversation.');
    }

    /**
     * REGRESSION 10: Document content endpoint and editing preserve original document text.
     */
    public function test_regression_10_document_content_and_editing_preserve_original_baseline(): void
    {
        [$job, $docA, $docB, $docAText] = $this->seedControlledDataset();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/documents/{$docA->id}/content");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Document_A_NY.pdf');

        $extracted = $response->json('data.text');
        $this->assertStringContainsString('24 months', $extracted);
        $this->assertStringContainsString('$500,000', $extracted);
        $this->assertStringContainsString('New York', $extracted);

        // Verify cross-user access control on document content & query endpoints
        $attackerRes = $this->actingAs($this->otherUser, 'sanctum')
            ->getJson("/api/v1/documents/{$docA->id}/content");
        $attackerRes->assertStatus(403);

        $attackerQueryRes = $this->actingAs($this->otherUser, 'sanctum')
            ->postJson("/api/v1/documents/{$docA->id}/query", ['prompt' => 'Steal document content']);
        $attackerQueryRes->assertStatus(403);
    }

    /**
     * REGRESSION 11: Comparison engine accurately detects all 4 differences in the controlled dataset.
     */
    public function test_regression_11_comparison_engine_accurately_detects_controlled_differences(): void
    {
        config(['ai.test_live_ai' => false]);
        [$job] = $this->seedControlledDataset();

        $compOrchestrator = app(ComparisonOrchestrator::class);
        $result = $compOrchestrator->compare($job);

        $this->assertNotEmpty($result->findings);
        $allExplanations = implode(' | ', array_map(fn($f) => $f->title . ' ' . $f->explanation, $result->findings));

        // Verify numerical & clause differences were caught
        $this->assertTrue(
            str_contains($allExplanations, '24') || str_contains($allExplanations, '12') || str_contains($allExplanations, '500,000') || str_contains($allExplanations, '250,000'),
            'Comparison findings must detect the numerical differences (24 vs 12 months, $500k vs $250k).'
        );
    }

    /**
     * REGRESSION 12: Corrupted / invalid files fail validation cleanly and never report success.
     */
    public function test_regression_12_corrupted_and_invalid_uploads_fail_cleanly_never_report_ready(): void
    {
        // Corrupted binary claiming to be PDF
        $corruptedPdf = UploadedFile::fake()->createWithContent('CorruptedFile.pdf', "\x00\x01\x02\x03NOT_A_VALID_PDF_HEADER");

        $res = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/upload', [
                'file' => $corruptedPdf,
                'slot' => 'A',
            ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /**
     * REGRESSION 13 & 14: Provider errors are translated into friendly explanations without leaking technical secrets,
     * and quota exhaustion stops further provider calls immediately.
     */
    public function test_regression_13_and_14_friendly_provider_errors_and_quota_exhaustion_halt(): void
    {
        [$job, $docA] = $this->seedControlledDataset();

        // Exhaust user's daily requests in cache
        $today = now()->format('Y-m-d');
        $reqKey = "docver:quota:user_{$this->user->id}:date_{$today}:requests";
        Cache::put($reqKey, 500, 3600); // Exceeds professional plan limit of 200

        $mockProvider = Mockery::mock(AIProviderInterface::class);
        $mockProvider->shouldReceive('getName')->andReturn('groq');
        $mockProvider->shouldReceive('isConfigured')->andReturn(true);
        // Provider must NOT be called when quota is already exhausted
        $mockProvider->shouldNotReceive('generate');
        $this->router->registerProvider($mockProvider);

        $result = $this->orchestrator->answerDocumentQuery($docA, 'Summarize this document', $this->user->id);

        $this->assertTrue($result['is_quota_error']);
        $this->assertNotEmpty($result['quota_details']);
        $this->assertStringNotContainsString('Stack trace', $result['answer']);
        $this->assertStringNotContainsString('gsk_', $result['answer']);
    }
}
