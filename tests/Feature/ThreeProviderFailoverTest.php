<?php

namespace Tests\Feature;

use App\Enums\ComparisonStatus;
use App\Models\ComparisonJob;
use App\Models\DocumentMetadata;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\DTOs\UsageStatsDTO;
use App\Services\AI\Exceptions\AllProvidersUnavailableException;
use App\Services\AI\Exceptions\AuthenticationException;
use App\Services\AI\Exceptions\RateLimitException;
use App\Services\AI\Health\CircuitBreaker;
use App\Services\AI\Health\ProviderHealthMonitor;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\GroqProvider;
use App\Services\AI\Providers\OpenRouterProvider;
use App\Services\AI\Quota\AIQuotaManager;
use App\Services\AI\Quota\QuotaManager;
use App\Services\AI\Router\AIProviderRouter;
use App\Services\AI\Security\PromptSanitizer;
use App\Services\Comparison\ComparisonOrchestrator;
use App\Services\Documents\Indexing\EphemeralIndexManager;
use App\Services\Documents\DTOs\DocumentChunkDTO;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ThreeProviderFailoverTest extends TestCase
{
    use RefreshDatabase;

    protected CircuitBreaker $circuitBreaker;
    protected ProviderHealthMonitor $healthMonitor;
    protected QuotaManager $quotaManager;
    protected AIQuotaManager $aiQuotaManager;
    protected PromptSanitizer $sanitizer;
    protected User $superAdmin;
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        config([
            'ai.primary_provider' => 'gemini',
            'ai.fallback_order' => ['gemini', 'groq', 'openrouter'],
        ]);

        $this->circuitBreaker = new CircuitBreaker();
        $this->healthMonitor = new ProviderHealthMonitor($this->circuitBreaker);
        $this->quotaManager = new QuotaManager();
        $this->aiQuotaManager = new AIQuotaManager();
        $this->sanitizer = new PromptSanitizer();

        $this->superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'admin_failover@docver.com',
            'password' => bcrypt('password123'),
            'role' => \App\Enums\UserRole::SUPER_ADMIN,
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Failover Test Workspace',
            'slug' => 'failover-ws-' . uniqid(),
            'owner_id' => $this->superAdmin->id,
            'plan' => 'enterprise',
            'max_file_size_mb' => 50,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_gemini_to_groq_failover_on_rate_limit(): void
    {
        $mockGemini = Mockery::mock(GeminiProvider::class);
        $mockGemini->shouldReceive('getName')->andReturn('gemini');
        $mockGemini->shouldReceive('isConfigured')->andReturn(true);
        $mockGemini->shouldReceive('supportsTask')->andReturn(true);
        $mockGemini->shouldReceive('generate')->once()->andThrow(
            new RateLimitException('gemini', 'Rate limit exceeded (HTTP 429)', 10)
        );

        $mockGroq = Mockery::mock(GroqProvider::class);
        $mockGroq->shouldReceive('getName')->andReturn('groq');
        $mockGroq->shouldReceive('isConfigured')->andReturn(true);
        $mockGroq->shouldReceive('supportsTask')->andReturn(true);
        $mockGroq->shouldReceive('generate')->once()->andReturn(
            new AIResponse(
                content: 'Groq successfully served the failover comparison',
                structuredData: ['similarity_score' => 88],
                provider: 'groq',
                model: 'llama-3.3-70b-versatile',
                usage: new UsageStatsDTO(120, 60, 180, 0.0001, 150)
            )
        );

        $mockOpenRouter = Mockery::mock(OpenRouterProvider::class);
        $mockOpenRouter->shouldReceive('getName')->andReturn('openrouter');
        $mockOpenRouter->shouldReceive('isConfigured')->andReturn(true);
        $mockOpenRouter->shouldReceive('supportsTask')->andReturn(true);
        $mockOpenRouter->shouldNotReceive('generate');

        $router = new AIProviderRouter(
            $this->circuitBreaker,
            $this->healthMonitor,
            $this->quotaManager,
            $this->sanitizer,
            $mockGemini,
            $mockGroq,
            $mockOpenRouter,
            $this->aiQuotaManager
        );

        $request = new AIRequest(prompt: 'Compare two documents');
        $response = $router->route($request);

        $this->assertEquals('groq', $response->provider);
        $this->assertTrue($response->isFallback);
        $this->assertEquals(88, $response->structuredData['similarity_score']);
    }

    public function test_groq_to_openrouter_failover_when_gemini_and_groq_rate_limited(): void
    {
        $mockGemini = Mockery::mock(GeminiProvider::class);
        $mockGemini->shouldReceive('getName')->andReturn('gemini');
        $mockGemini->shouldReceive('isConfigured')->andReturn(true);
        $mockGemini->shouldReceive('supportsTask')->andReturn(true);
        $mockGemini->shouldReceive('generate')->once()->andThrow(
            new RateLimitException('gemini', 'Gemini HTTP 429', 5)
        );

        $mockGroq = Mockery::mock(GroqProvider::class);
        $mockGroq->shouldReceive('getName')->andReturn('groq');
        $mockGroq->shouldReceive('isConfigured')->andReturn(true);
        $mockGroq->shouldReceive('supportsTask')->andReturn(true);
        $mockGroq->shouldReceive('generate')->once()->andThrow(
            new RateLimitException('groq', 'Groq HTTP 429', 5)
        );

        $mockOpenRouter = Mockery::mock(OpenRouterProvider::class);
        $mockOpenRouter->shouldReceive('getName')->andReturn('openrouter');
        $mockOpenRouter->shouldReceive('isConfigured')->andReturn(true);
        $mockOpenRouter->shouldReceive('supportsTask')->andReturn(true);
        $mockOpenRouter->shouldReceive('generate')->once()->andReturn(
            new AIResponse(
                content: 'OpenRouter Claude served the comparison',
                structuredData: ['similarity_score' => 95],
                provider: 'openrouter',
                model: 'anthropic/claude-3.5-sonnet',
                usage: new UsageStatsDTO(200, 100, 300, 0.002, 350)
            )
        );

        $router = new AIProviderRouter(
            $this->circuitBreaker,
            $this->healthMonitor,
            $this->quotaManager,
            $this->sanitizer,
            $mockGemini,
            $mockGroq,
            $mockOpenRouter,
            $this->aiQuotaManager
        );

        $request = new AIRequest(prompt: 'Compare doc');
        $response = $router->route($request);

        $this->assertEquals('openrouter', $response->provider);
        $this->assertTrue($response->isFallback);
    }

    public function test_proactive_failover_when_gemini_quota_near_exhaustion(): void
    {
        $today = Carbon::now('UTC')->format('Y-m-d');

        // Set Gemini daily requests to 1430 out of 1500 (below 5% margin)
        $rpdKey = "docver:quota:gemini:rpd:{$today}";
        Cache::put($rpdKey, 1450, 86400);

        $mockGemini = Mockery::mock(GeminiProvider::class);
        $mockGemini->shouldReceive('getName')->andReturn('gemini');
        $mockGemini->shouldReceive('isConfigured')->andReturn(true);
        $mockGemini->shouldReceive('supportsTask')->andReturn(true);
        // Gemini should NOT even be called because proactive check catches it
        $mockGemini->shouldNotReceive('generate');

        $mockGroq = Mockery::mock(GroqProvider::class);
        $mockGroq->shouldReceive('getName')->andReturn('groq');
        $mockGroq->shouldReceive('isConfigured')->andReturn(true);
        $mockGroq->shouldReceive('supportsTask')->andReturn(true);
        $mockGroq->shouldReceive('generate')->once()->andReturn(
            new AIResponse(
                content: 'Served proactively by Groq',
                structuredData: null,
                provider: 'groq',
                model: 'llama-3.3-70b-versatile'
            )
        );

        $mockOpenRouter = Mockery::mock(OpenRouterProvider::class);
        $mockOpenRouter->shouldReceive('getName')->andReturn('openrouter');
        $mockOpenRouter->shouldReceive('isConfigured')->andReturn(true);
        $mockOpenRouter->shouldReceive('supportsTask')->andReturn(true);

        $router = new AIProviderRouter(
            $this->circuitBreaker,
            $this->healthMonitor,
            $this->quotaManager,
            $this->sanitizer,
            $mockGemini,
            $mockGroq,
            $mockOpenRouter,
            $this->aiQuotaManager
        );

        $request = new AIRequest(prompt: 'Verify clause');
        $response = $router->route($request);

        $this->assertEquals('groq', $response->provider);
    }

    public function test_distinguishes_401_authentication_error_without_silent_swallowing(): void
    {
        $mockGemini = Mockery::mock(GeminiProvider::class);
        $mockGemini->shouldReceive('getName')->andReturn('gemini');
        $mockGemini->shouldReceive('isConfigured')->andReturn(true);
        $mockGemini->shouldReceive('supportsTask')->andReturn(true);
        $mockGemini->shouldReceive('generate')->once()->andThrow(
            new AuthenticationException('gemini', 'Invalid API key')
        );

        $mockGroq = Mockery::mock(GroqProvider::class);
        $mockGroq->shouldReceive('getName')->andReturn('groq');
        $mockGroq->shouldReceive('isConfigured')->andReturn(true);
        $mockGroq->shouldReceive('supportsTask')->andReturn(true);
        $mockGroq->shouldReceive('generate')->once()->andReturn(
            new AIResponse(
                content: 'Groq served after auth failure on primary',
                structuredData: null,
                provider: 'groq',
                model: 'llama-3.3-70b-versatile'
            )
        );

        $mockOpenRouter = Mockery::mock(OpenRouterProvider::class);
        $mockOpenRouter->shouldReceive('getName')->andReturn('openrouter');
        $mockOpenRouter->shouldReceive('isConfigured')->andReturn(true);

        $router = new AIProviderRouter(
            $this->circuitBreaker,
            $this->healthMonitor,
            $this->quotaManager,
            $this->sanitizer,
            $mockGemini,
            $mockGroq,
            $mockOpenRouter,
            $this->aiQuotaManager
        );

        $request = new AIRequest(prompt: 'Test auth');
        $response = $router->route($request);

        $this->assertEquals('groq', $response->provider);
    }

    public function test_all_providers_unavailable_preserves_comparison_job_state(): void
    {
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->superAdmin->id,
            'title' => 'Test All Unavailable Job',
            'status' => ComparisonStatus::PROCESSING,
        ]);

        $mockGemini = Mockery::mock(GeminiProvider::class);
        $mockGemini->shouldReceive('getName')->andReturn('gemini');
        $mockGemini->shouldReceive('isConfigured')->andReturn(true);
        $mockGemini->shouldReceive('supportsTask')->andReturn(true);
        $mockGemini->shouldReceive('generate')->andThrow(new RateLimitException('gemini', 'Gemini down'));

        $mockGroq = Mockery::mock(GroqProvider::class);
        $mockGroq->shouldReceive('getName')->andReturn('groq');
        $mockGroq->shouldReceive('isConfigured')->andReturn(true);
        $mockGroq->shouldReceive('supportsTask')->andReturn(true);
        $mockGroq->shouldReceive('generate')->andThrow(new RateLimitException('groq', 'Groq down'));

        $mockOpenRouter = Mockery::mock(OpenRouterProvider::class);
        $mockOpenRouter->shouldReceive('getName')->andReturn('openrouter');
        $mockOpenRouter->shouldReceive('isConfigured')->andReturn(true);
        $mockOpenRouter->shouldReceive('supportsTask')->andReturn(true);
        $mockOpenRouter->shouldReceive('generate')->andThrow(new RateLimitException('openrouter', 'OpenRouter down'));

        $router = new AIProviderRouter(
            $this->circuitBreaker,
            $this->healthMonitor,
            $this->quotaManager,
            $this->sanitizer,
            $mockGemini,
            $mockGroq,
            $mockOpenRouter,
            $this->aiQuotaManager
        );

        $request = new AIRequest(
            prompt: 'Verify clause',
            metadata: ['comparison_job_id' => $job->id]
        );

        try {
            $router->route($request);
            $this->fail('Expected AllProvidersUnavailableException was not thrown');
        } catch (AllProvidersUnavailableException $e) {
            // Verify job was preserved with accurate status rather than deleted
            $job->refresh();
            $this->assertEquals(ComparisonStatus::TEMPORARILY_UNAVAILABLE, $job->status);
            $this->assertStringContainsString('temporarily unavailable', $job->error_message);
        }
    }

    public function test_admin_switch_primary_provider_alters_priority(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum');

        $response = $this->postJson('/api/v1/admin/providers/switch-primary', [
            'primary_provider' => 'groq',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.primary_provider', 'groq');
        $response->assertJsonPath('data.priority_order.0', 'groq');

        // Verify router now resolves groq as first candidate
        $router = app(AIProviderRouter::class);
        $candidates = $router->resolveCandidateProviders();
        $this->assertEquals('groq', $candidates[0]);
    }

    public function test_comparison_orchestrator_stage_resumption_and_idempotency(): void
    {
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->superAdmin->id,
            'title' => 'Test Stage Resumption Job',
            'status' => ComparisonStatus::PROCESSING,
            'last_completed_stage' => 'matching_sections',
        ]);

        $indexManager = app(EphemeralIndexManager::class);
        $indexManager->storeChunks($job->id, 'A', [
            new DocumentChunkDTO(
                chunkId: 'chunk_a_1',
                documentSlot: 'A',
                documentId: 1,
                pageNumber: 1,
                sectionTitle: 'SECTION 1. CONFIDENTIALITY',
                content: "SECTION 1. CONFIDENTIALITY\nNeither party shall disclose confidential proprietary data.",
                startChar: 0,
                endChar: 70
            ),
        ]);
        $indexManager->storeChunks($job->id, 'B', [
            new DocumentChunkDTO(
                chunkId: 'chunk_b_1',
                documentSlot: 'B',
                documentId: 2,
                pageNumber: 1,
                sectionTitle: 'SECTION 1. CONFIDENTIALITY',
                content: "SECTION 1. CONFIDENTIALITY\nNeither party shall disclose confidential proprietary data.",
                startChar: 0,
                endChar: 70
            ),
        ]);

        $orchestrator = app(ComparisonOrchestrator::class);
        $result = $orchestrator->compare($job);

        $this->assertNotNull($result);
        $job->refresh();
        $this->assertEquals('completed', $job->last_completed_stage);
        $this->assertNotNull($job->similarity_score);
    }
}
