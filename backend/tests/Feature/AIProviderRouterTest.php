<?php

namespace Tests\Feature;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\DTOs\UsageStatsDTO;
use App\Services\AI\Exceptions\AllProvidersUnavailableException;
use App\Services\AI\Exceptions\AuthenticationException;
use App\Services\AI\Exceptions\InvalidStructuredOutputException;
use App\Services\AI\Exceptions\ProviderServerException;
use App\Services\AI\Exceptions\QuotaExceededException;
use App\Services\AI\Exceptions\RateLimitException;
use App\Services\AI\Health\CircuitBreaker;
use App\Services\AI\Health\ProviderHealthMonitor;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\OpenRouterProvider;
use App\Services\AI\Quota\QuotaManager;
use App\Services\AI\Router\AIProviderRouter;
use App\Services\AI\Security\PromptSanitizer;
use Generator;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class AIProviderRouterTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected CircuitBreaker $circuitBreaker;
    protected ProviderHealthMonitor $healthMonitor;
    protected QuotaManager $quotaManager;
    protected PromptSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->circuitBreaker = new CircuitBreaker();
        $this->healthMonitor = new ProviderHealthMonitor($this->circuitBreaker);
        $this->quotaManager = new QuotaManager();
        $this->sanitizer = new PromptSanitizer();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_primary_provider_success(): void
    {
        $mockGemini = Mockery::mock(GeminiProvider::class);
        $mockGemini->shouldReceive('getName')->andReturn('gemini');
        $mockGemini->shouldReceive('isConfigured')->andReturn(true);
        $mockGemini->shouldReceive('generate')->once()->andReturn(
            new AIResponse(
                content: 'Verified comparison summary',
                structuredData: ['similarity_score' => 92],
                provider: 'gemini',
                model: 'gemini-2.0-flash',
                usage: new UsageStatsDTO(100, 50, 150, 0.0001, 250)
            )
        );

        $mockOpenRouter = Mockery::mock(OpenRouterProvider::class);
        $mockOpenRouter->shouldReceive('getName')->andReturn('openrouter');
        $mockOpenRouter->shouldReceive('isConfigured')->andReturn(true);
        $mockOpenRouter->shouldNotReceive('generate');

        $router = new AIProviderRouter(
            $this->circuitBreaker,
            $this->healthMonitor,
            $this->quotaManager,
            $this->sanitizer,
            $mockGemini,
            $mockOpenRouter
        );

        $request = new AIRequest(prompt: 'Compare doc A and B');
        $response = $router->route($request);

        $this->assertEquals('gemini', $response->provider);
        $this->assertFalse($response->isFallback);
        $this->assertEquals(92, $response->structuredData['similarity_score']);
        $this->assertEquals(CircuitBreaker::STATE_CLOSED, $this->circuitBreaker->getState('gemini'));
    }

    public function test_secondary_failover_when_primary_fails_with_500(): void
    {
        $mockGemini = Mockery::mock(GeminiProvider::class);
        $mockGemini->shouldReceive('getName')->andReturn('gemini');
        $mockGemini->shouldReceive('isConfigured')->andReturn(true);
        $mockGemini->shouldReceive('generate')->once()->andThrow(
            new ProviderServerException('gemini', 'Internal server error', 500)
        );

        $mockOpenRouter = Mockery::mock(OpenRouterProvider::class);
        $mockOpenRouter->shouldReceive('getName')->andReturn('openrouter');
        $mockOpenRouter->shouldReceive('isConfigured')->andReturn(true);
        $mockOpenRouter->shouldReceive('generate')->once()->andReturn(
            new AIResponse(
                content: 'Failover summary from Claude',
                structuredData: ['similarity_score' => 88],
                provider: 'openrouter',
                model: 'anthropic/claude-3.5-sonnet',
                usage: new UsageStatsDTO(120, 60, 180, 0.001, 450)
            )
        );

        $router = new AIProviderRouter(
            $this->circuitBreaker,
            $this->healthMonitor,
            $this->quotaManager,
            $this->sanitizer,
            $mockGemini,
            $mockOpenRouter
        );

        $request = new AIRequest(prompt: 'Compare doc A and B');
        $response = $router->route($request);

        $this->assertEquals('openrouter', $response->provider);
        $this->assertTrue($response->isFallback);
    }

    public function test_rate_limit_failover_respects_retry_after(): void
    {
        $mockGemini = Mockery::mock(GeminiProvider::class);
        $mockGemini->shouldReceive('getName')->andReturn('gemini');
        $mockGemini->shouldReceive('isConfigured')->andReturn(true);
        $mockGemini->shouldReceive('generate')->once()->andThrow(
            new RateLimitException('gemini', 'Too Many Requests', 30)
        );

        $mockOpenRouter = Mockery::mock(OpenRouterProvider::class);
        $mockOpenRouter->shouldReceive('getName')->andReturn('openrouter');
        $mockOpenRouter->shouldReceive('isConfigured')->andReturn(true);
        $mockOpenRouter->shouldReceive('generate')->once()->andReturn(
            new AIResponse(
                content: 'OpenRouter handled after Gemini 429',
                provider: 'openrouter',
                model: 'openai/gpt-4o'
            )
        );

        $router = new AIProviderRouter(
            $this->circuitBreaker,
            $this->healthMonitor,
            $this->quotaManager,
            $this->sanitizer,
            $mockGemini,
            $mockOpenRouter
        );

        $response = $router->route(new AIRequest(prompt: 'Audit redline'));

        $this->assertEquals('openrouter', $response->provider);
        $this->assertTrue($response->isFallback);
    }

    public function test_invalid_credentials_failover(): void
    {
        $mockGemini = Mockery::mock(GeminiProvider::class);
        $mockGemini->shouldReceive('getName')->andReturn('gemini');
        $mockGemini->shouldReceive('isConfigured')->andReturn(true);
        $mockGemini->shouldReceive('generate')->once()->andThrow(
            new AuthenticationException('gemini', 'Invalid API Key')
        );

        $mockOpenRouter = Mockery::mock(OpenRouterProvider::class);
        $mockOpenRouter->shouldReceive('getName')->andReturn('openrouter');
        $mockOpenRouter->shouldReceive('isConfigured')->andReturn(true);
        $mockOpenRouter->shouldReceive('generate')->once()->andReturn(
            new AIResponse(
                content: 'Processed successfully by secondary provider',
                provider: 'openrouter',
                model: 'anthropic/claude-3.5-sonnet'
            )
        );

        $router = new AIProviderRouter(
            $this->circuitBreaker,
            $this->healthMonitor,
            $this->quotaManager,
            $this->sanitizer,
            $mockGemini,
            $mockOpenRouter
        );

        $response = $router->route(new AIRequest(prompt: 'Analyze clauses'));
        $this->assertEquals('openrouter', $response->provider);
        $this->assertTrue($response->isFallback);
    }

    public function test_invalid_structured_output_triggers_failover(): void
    {
        $mockGemini = Mockery::mock(GeminiProvider::class);
        $mockGemini->shouldReceive('getName')->andReturn('gemini');
        $mockGemini->shouldReceive('isConfigured')->andReturn(true);
        $mockGemini->shouldReceive('generate')->once()->andThrow(
            new InvalidStructuredOutputException('gemini', 'Malformed JSON', 'not a json')
        );

        $mockOpenRouter = Mockery::mock(OpenRouterProvider::class);
        $mockOpenRouter->shouldReceive('getName')->andReturn('openrouter');
        $mockOpenRouter->shouldReceive('isConfigured')->andReturn(true);
        $mockOpenRouter->shouldReceive('generate')->once()->andReturn(
            new AIResponse(
                content: '{"similarity_score": 85}',
                structuredData: ['similarity_score' => 85],
                provider: 'openrouter',
                model: 'anthropic/claude-3.5-sonnet'
            )
        );

        $router = new AIProviderRouter(
            $this->circuitBreaker,
            $this->healthMonitor,
            $this->quotaManager,
            $this->sanitizer,
            $mockGemini,
            $mockOpenRouter
        );

        $request = new AIRequest(prompt: 'Compare', jsonSchema: ['type' => 'object']);
        $response = $router->route($request);

        $this->assertEquals('openrouter', $response->provider);
        $this->assertEquals(85, $response->structuredData['similarity_score']);
    }

    public function test_throws_all_providers_unavailable_when_all_fail(): void
    {
        $mockGemini = Mockery::mock(GeminiProvider::class);
        $mockGemini->shouldReceive('getName')->andReturn('gemini');
        $mockGemini->shouldReceive('isConfigured')->andReturn(true);
        $mockGemini->shouldReceive('generate')->once()->andThrow(
            new ProviderServerException('gemini', 'Gemini down')
        );

        $mockOpenRouter = Mockery::mock(OpenRouterProvider::class);
        $mockOpenRouter->shouldReceive('getName')->andReturn('openrouter');
        $mockOpenRouter->shouldReceive('isConfigured')->andReturn(true);
        $mockOpenRouter->shouldReceive('generate')->once()->andThrow(
            new ProviderServerException('openrouter', 'OpenRouter down')
        );

        $router = new AIProviderRouter(
            $this->circuitBreaker,
            $this->healthMonitor,
            $this->quotaManager,
            $this->sanitizer,
            $mockGemini,
            $mockOpenRouter
        );

        $this->expectException(AllProvidersUnavailableException::class);
        $router->route(new AIRequest(prompt: 'Audit'));
    }
}
