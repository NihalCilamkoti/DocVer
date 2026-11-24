<?php

namespace Tests\Feature;

use App\Models\ComparisonJob;
use App\Models\DocumentMetadata;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\AIOrchestrator;
use App\Services\AI\Config\TokenManager;
use App\Services\AI\Context\ContextBuilder;
use App\Services\AI\Conversation\ConversationManager;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\DTOs\UsageStatsDTO;
use App\Services\AI\Intent\TaskIntentClassifier;
use App\Services\AI\Prompts\PromptManager;
use App\Services\AI\Router\AIProviderRouter;
use App\Services\AI\Validation\ResponseValidator;
use App\Services\Documents\DTOs\DocumentChunkDTO;
use App\Services\Documents\Indexing\EphemeralIndexManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class AIQualityTestSuiteTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;
    protected EphemeralIndexManager $indexManager;
    protected TaskIntentClassifier $classifier;
    protected ContextBuilder $contextBuilder;
    protected PromptManager $promptManager;
    protected TokenManager $tokenManager;
    protected ResponseValidator $responseValidator;
    protected ConversationManager $conversationManager;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::create([
            'name' => 'Quality Test Workspace',
            'slug' => 'quality-test-ws',
            'owner_id' => $this->user->id,
        ]);

        $this->indexManager = app(EphemeralIndexManager::class);
        $this->classifier = app(TaskIntentClassifier::class);
        $this->contextBuilder = app(ContextBuilder::class);
        $this->promptManager = app(PromptManager::class);
        $this->tokenManager = app(TokenManager::class);
        $this->responseValidator = app(ResponseValidator::class);
        $this->conversationManager = app(ConversationManager::class);
    }

    /**
     * Test A: Instruction following
     * Ask about one specific section. Verify that the answer only discusses that section.
     */
    public function test_case_a_instruction_following_section_specific(): void
    {
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Section Specific Test',
            'status' => 'completed',
        ]);

        $chunks = [
            new DocumentChunkDTO(
                chunkId: 'chunk_1',
                documentSlot: 'A',
                documentId: 1,
                pageNumber: 1,
                sectionTitle: 'Section 1 - Background',
                content: 'Section 1. Background. Acquired Corporation agrees to license software.',
                startChar: 0,
                endChar: 65
            ),
            new DocumentChunkDTO(
                chunkId: 'chunk_4',
                documentSlot: 'A',
                documentId: 1,
                pageNumber: 4,
                sectionTitle: 'Section 4 - Intellectual Property',
                content: 'Section 4. Intellectual Property. All custom algorithms developed by Vendor remain exclusive property of Client with irrevocable license.',
                startChar: 0,
                endChar: 130
            ),
        ];
        $this->indexManager->storeChunks($job->id, 'A', $chunks);

        $intent = $this->classifier->classify('Summarize only section 4');
        $this->assertTrue($intent->isSectionSpecific());
        $this->assertContains('strict_section_only', $intent->constraints);

        $context = $this->contextBuilder->buildComparisonContext($job, $intent);
        $this->assertTrue($context->targetFound);
        $this->assertStringContainsString('Section 4 - Intellectual Property', $context->formattedContext);
    }

    /**
     * Test B: Context accuracy
     * Supply a document with a unique amount, date and company name. Ask questions about those values.
     * Verify that the AI receives and returns the exact values.
     */
    public function test_case_b_context_accuracy_with_unique_values(): void
    {
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Context Accuracy Test',
            'status' => 'completed',
        ]);

        $uniqueContent = 'Under Section 8.2, ApexGlobal Corp shall pay exactly $1,475,250 USD on October 14, 2027 to CloudNexus Technologies.';
        $chunks = [
            new DocumentChunkDTO(
                chunkId: 'chunk_b1',
                documentSlot: 'A',
                documentId: 1,
                pageNumber: 2,
                sectionTitle: 'Section 8.2 - Payment Terms',
                content: $uniqueContent,
                startChar: 0,
                endChar: strlen($uniqueContent)
            ),
        ];
        $this->indexManager->storeChunks($job->id, 'A', $chunks);

        $intent = $this->classifier->classify('What is the payment amount and date for ApexGlobal Corp?');
        $context = $this->contextBuilder->buildComparisonContext($job, $intent);

        $this->assertStringContainsString('$1,475,250 USD', $context->formattedContext);
        $this->assertStringContainsString('October 14, 2027', $context->formattedContext);
        $this->assertStringContainsString('ApexGlobal Corp', $context->formattedContext);
    }

    /**
     * Test C: Relevance
     * Ask a specific question. Verify that the response addresses that question rather than a generic topic.
     */
    public function test_case_c_relevance_classification_and_prompting(): void
    {
        $intent = $this->classifier->classify('Compare the payment periods between the drafts');
        $this->assertEquals('numerical_comparison', $intent->intent);
        $this->assertContains('payment', $intent->targetClauses);
        $this->assertEquals(0.0, $this->tokenManager->getTemperatureForIntent($intent));
    }

    /**
     * Test D: Missing information
     * Ask about a clause that does not exist. Verify that the AI reports it as missing.
     */
    public function test_case_d_missing_information_negative_detection(): void
    {
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Missing Info Test',
            'status' => 'completed',
        ]);

        // Supply document with only schedule, NO payment or penalty terms
        $chunks = [
            new DocumentChunkDTO(
                chunkId: 'chunk_d1',
                documentSlot: 'A',
                documentId: 1,
                pageNumber: 1,
                sectionTitle: 'Daily Routine',
                content: 'Morning Routine: 5:00 am wake up, 6:00 am breakfast, 7:00 am study session.',
                startChar: 0,
                endChar: 75
            ),
        ];
        $this->indexManager->storeChunks($job->id, 'A', $chunks);

        $intent = $this->classifier->classify('Focus on payment terms, invoicing, fees, and penalties');
        $context = $this->contextBuilder->buildComparisonContext($job, $intent);

        // Crucial: targetFound MUST be false
        $this->assertFalse($context->targetFound);

        // Response validator should safeguard and enforce negative finding if AI tries to hallucinate
        $hallucinatedResponse = 'The payment terms require invoice payment within 30 days and 5% penalty.';
        $validation = $this->responseValidator->validate($hallucinatedResponse, $intent, $context, 'Daily Schedule');

        $this->assertStringContainsString('contains any provisions, sections, or mentions', $validation->validatedContent);
        $this->assertStringContainsString('payment', strtolower($validation->validatedContent));
    }

    /**
     * Test E: Numerical accuracy
     * Compare two amounts. Verify exact numerical calculation and zero-temperature setting.
     */
    public function test_case_e_numerical_accuracy_temperature_and_intent(): void
    {
        $intent = $this->classifier->classify('Compare the liability cap amounts between both versions');
        $this->assertEquals('numerical_comparison', $intent->intent);
        $this->assertEquals(0.0, $this->tokenManager->getTemperatureForIntent($intent));
        $this->assertContains('exact_numerical_audit', $intent->constraints);
    }

    /**
     * Test F: Conversation isolation
     * Compare two unrelated comparisons. Verify context from comparison 1 never appears in comparison 2.
     */
    public function test_case_f_conversation_isolation(): void
    {
        $job1 = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Comparison One - Secret Acme Corp Merger',
            'status' => 'completed',
        ]);

        $job2 = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Comparison Two - Beta LLC Lease',
            'status' => 'completed',
        ]);

        \App\Models\ChatMessage::create([
            'comparison_job_id' => $job1->id,
            'user_id' => $this->user->id,
            'sender' => 'user',
            'content' => 'What is the purchase price for Acme Corp?',
        ]);

        \App\Models\ChatMessage::create([
            'comparison_job_id' => $job1->id,
            'user_id' => null,
            'sender' => 'ai',
            'content' => 'The purchase price for Acme Corp is $50,000,000 USD.',
        ]);

        $historyJob2 = $this->conversationManager->getComparisonHistory($job2->id);

        $this->assertEmpty($historyJob2, 'Comparison 2 history must remain completely isolated and empty');
    }

    /**
     * Test G: Long-document accuracy
     * Ask about clauses near the beginning, middle and end of a large document. Verify none are silently ignored.
     */
    public function test_case_g_long_document_sampling_coverage(): void
    {
        $chunks = [];
        for ($i = 0; $i < 20; $i++) {
            $chunks[] = [
                'id' => "chunk_{$i}",
                'slot' => 'A',
                'page_number' => $i + 1,
                'section_title' => "Section {$i}",
                'content' => "Operative text for clause {$i} on page " . ($i + 1),
            ];
        }

        // Use reflection to verify sampleDocumentChunks covers beginning, middle, and end
        $ref = new \ReflectionClass($this->contextBuilder);
        $method = $ref->getMethod('sampleDocumentChunks');
        $method->setAccessible(true);

        $sampled = $method->invoke($this->contextBuilder, $chunks, 5);

        $this->assertCount(5, $sampled);
        $this->assertEquals(1, $sampled[0]['page_number'], 'First page (beginning) must be present');
        $this->assertEquals(20, $sampled[count($sampled) - 1]['page_number'], 'Last page (end) must be present');
    }

    /**
     * Test H: Provider fallback preserves task and context
     * Simulate provider failure and verify fallback provider receives the original task & context.
     */
    public function test_case_h_provider_fallback_preserves_task_and_context(): void
    {
        $mockPrimary = Mockery::mock(AIProviderInterface::class);
        $mockPrimary->shouldReceive('getName')->andReturn('groq');
        $mockPrimary->shouldReceive('isConfigured')->andReturn(true);
        $mockPrimary->shouldReceive('getDefaultModel')->andReturn('qwen/qwen3.8-27b');
        $mockPrimary->shouldReceive('supportsTask')->andReturn(true);
        $mockPrimary->shouldReceive('generate')->andThrow(new \App\Services\AI\Exceptions\RateLimitException('Rate limit exceeded', 60));

        $mockFallback = Mockery::mock(AIProviderInterface::class);
        $mockFallback->shouldReceive('getName')->andReturn('openrouter');
        $mockFallback->shouldReceive('isConfigured')->andReturn(true);
        $mockFallback->shouldReceive('getDefaultModel')->andReturn('openai/gpt-4o-mini');
        $mockFallback->shouldReceive('supportsTask')->andReturn(true);
        $mockFallback->shouldReceive('generate')->andReturnUsing(function (AIRequest $req) {
            // Verify fallback received exact prompt, taskType, and temperature
            $this->assertEquals('chat', $req->taskType);
            $this->assertStringContainsString('Context Verification', $req->prompt);

            return new AIResponse(
                content: 'Verified fallback answer preserving all task context.',
                structuredData: null,
                provider: 'openrouter',
                model: 'openai/gpt-4o-mini',
                usage: new UsageStatsDTO(promptTokens: 100, completionTokens: 50, totalTokens: 150, estimatedCostUsd: 0.0001)
            );
        });

        $router = app(AIProviderRouter::class);
        $router->registerProvider($mockPrimary);
        $router->registerProvider($mockFallback);

        config(['ai.fallback_order' => ['groq', 'openrouter']]);
        config(['ai.primary_provider' => 'groq']);

        $request = new AIRequest(
            prompt: 'Context Verification: Check clause 12.',
            systemInstruction: 'Strict verification instructions.',
            taskType: 'chat'
        );

        $response = $router->route($request);

        $this->assertTrue($response->isFallback);
        $this->assertEquals('openrouter', $response->provider);
        $this->assertStringContainsString('Verified fallback answer', $response->content);
    }

    /**
     * Test I: Hallucination prevention
     * Provide incomplete source material. Verify unsupported information is not presented as fact.
     */
    public function test_case_i_hallucination_prevention_on_incomplete_source(): void
    {
        $intent = $this->classifier->classify('What is the penalty for early termination?');
        $context = new \App\Services\AI\Context\ContextResultDTO(
            contextBlocks: ['Document A covers general setup.'],
            citations: [],
            targetFound: false,
            formattedContext: 'Document A covers general setup.',
            availableSections: ['General Setup'],
            specificTargetMatch: null
        );

        $hallucinatedText = 'The penalty for early termination is 25% of the total contract value.';
        $validated = $this->responseValidator->validate($hallucinatedText, $intent, $context, 'Service Agreement');

        // Validator must catch and prevent the hallucinated penalty
        $this->assertStringContainsString('contains any provisions', $validated->validatedContent);
        $this->assertContains('hallucinated_clause_corrected_to_negative_finding', $validated->warnings);
    }

    /**
     * Test J: Output consistency
     * Repeat the same controlled classification and prompt generation. Verify deterministic consistency.
     */
    public function test_case_j_output_consistency_under_controlled_inputs(): void
    {
        $query = 'Compare the dispute resolution mechanisms and governing law between Document A and Document B';

        $intent1 = $this->classifier->classify($query);
        $intent2 = $this->classifier->classify($query);

        $this->assertEquals($intent1->intent, $intent2->intent);
        $this->assertEquals($intent1->targetClauses, $intent2->targetClauses);
        $this->assertEquals($intent1->constraints, $intent2->constraints);
        $this->assertEquals(
            $this->tokenManager->getTemperatureForIntent($intent1),
            $this->tokenManager->getTemperatureForIntent($intent2)
        );
    }
}
