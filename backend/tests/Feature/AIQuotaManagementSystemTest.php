<?php

namespace Tests\Feature;

use App\Models\ComparisonJob;
use App\Models\DocumentMetadata;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\Exceptions\AllProvidersUnavailableException;
use App\Services\AI\Exceptions\AuthenticationException;
use App\Services\AI\Exceptions\RateLimitException;
use App\Services\AI\Quota\AIQuotaResolver;
use App\Services\AI\Quota\QuotaManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AIQuotaManagementSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;
    protected AIQuotaResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->user = User::factory()->create([
            'email' => 'lawyer@example.com',
            'role' => 'user',
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Legal Department',
            'slug' => 'legal-department-' . uniqid(),
            'owner_id' => $this->user->id,
            'plan' => 'free',
        ]);
        $this->workspace->members()->attach($this->user, ['role' => 'workspace_admin']);

        $this->resolver = app(AIQuotaResolver::class);
    }

    /**
     * Test 1: User daily quota limit calculation & friendly human-readable message.
     */
    public function test_user_daily_request_limit_enforcement_and_reset_time_calculation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-22 14:30:00', 'UTC'));

        $quotaDTO = $this->resolver->resolveUserQuotaLimit($this->user->id, 'free', 'UTC');

        $this->assertTrue($quotaDTO->isQuotaError);
        $this->assertEquals('daily_user', $quotaDTO->type);
        $this->assertFalse($quotaDTO->retryAllowed);

        // 14:30 UTC -> midnight reset is 9 hours and 30 minutes away
        $this->assertGreaterThanOrEqual(34200, $quotaDTO->remainingSeconds);
        $this->assertLessThanOrEqual(34201, $quotaDTO->remainingSeconds);
        $this->assertStringContainsString('9 hours and 30 minutes', $quotaDTO->remainingTimeHuman);
        $this->assertStringContainsString('tomorrow at 12:00 AM UTC', $quotaDTO->resetsAtHuman);
        $this->assertStringContainsString("You've used all your AI requests for today", $quotaDTO->friendlyMessage);
        $this->assertStringContainsString('Free plan', $quotaDTO->friendlyMessage);

        Carbon::setTestNow();
    }

    /**
     * Test 2: Provider rate limit (429) friendly message and cooldown.
     */
    public function test_provider_rate_limit_429_returns_friendly_cooldown(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-22 10:00:00', 'UTC'));

        $quotaDTO = $this->resolver->resolveProviderRateLimit('groq', 45);

        $this->assertTrue($quotaDTO->isQuotaError);
        $this->assertEquals('provider_rate_limit', $quotaDTO->type);
        $this->assertTrue($quotaDTO->retryAllowed);
        $this->assertEquals(45, $quotaDTO->remainingSeconds);
        $this->assertEquals('45 seconds', $quotaDTO->remainingTimeHuman);
        $this->assertStringContainsString('Groq', $quotaDTO->friendlyMessage);
        $this->assertStringContainsString('Please try again in 45 seconds', $quotaDTO->friendlyMessage);

        Carbon::setTestNow();
    }

    /**
     * Test 3: All providers exhausted produces a structured, polite apology without crashing.
     */
    public function test_all_providers_exhausted_returns_polite_message_never_crashes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-22 18:00:00', 'UTC'));

        $quotaDTO = $this->resolver->resolveAllProvidersExhausted('openrouter');

        $this->assertTrue($quotaDTO->isQuotaError);
        $this->assertEquals('provider_daily', $quotaDTO->type);
        $this->assertFalse($quotaDTO->retryAllowed);
        $this->assertStringContainsString('temporarily reached its available usage limits across all configured providers', $quotaDTO->friendlyMessage);
        $this->assertStringContainsString('Access will resume in approximately 6 hours', $quotaDTO->friendlyMessage);

        Carbon::setTestNow();
    }

    /**
     * Test 4: Invalid API key / auth error is reported as a configuration issue, NOT a daily limit.
     */
    public function test_invalid_api_key_reports_service_configuration_issue(): void
    {
        $authException = new AuthenticationException('Groq API authentication failed: 401 Unauthorized invalid_api_key');

        $quotaDTO = $this->resolver->resolveGeneralError($authException, 'groq');

        $this->assertTrue($quotaDTO->isQuotaError);
        $this->assertEquals('invalid_config', $quotaDTO->type);
        $this->assertFalse($quotaDTO->retryAllowed);
        $this->assertStringContainsString('configuration update', $quotaDTO->friendlyMessage);
        $this->assertStringNotContainsString("daily limit has been reached", $quotaDTO->friendlyMessage);
    }

    /**
     * Test 5: Temporary provider outage is classified cleanly.
     */
    public function test_temporary_provider_outage_returns_outage_notice(): void
    {
        $outageException = new \RuntimeException('Upstream connection timeout to Gemini API endpoint: 504 Gateway Timeout');

        $quotaDTO = $this->resolver->resolveGeneralError($outageException, 'gemini');

        $this->assertTrue($quotaDTO->isQuotaError);
        $this->assertEquals('provider_outage', $quotaDTO->type);
        $this->assertTrue($quotaDTO->retryAllowed);
        $this->assertStringContainsString('temporarily unavailable', $quotaDTO->friendlyMessage);
    }

    /**
     * Test 6: Precise human readable duration calculations.
     */
    public function test_human_readable_time_formatting(): void
    {
        $this->assertEquals('30 seconds', $this->resolver->formatRemainingTime(30));
        $this->assertEquals('1 minute', $this->resolver->formatRemainingTime(60));
        $this->assertEquals('2 minutes and 15 seconds', $this->resolver->formatRemainingTime(135));
        $this->assertEquals('1 hour and 15 minutes', $this->resolver->formatRemainingTime(4500));
        $this->assertEquals('3 hours and 20 minutes', $this->resolver->formatRemainingTime(12000));
    }

    /**
     * Test 7: QuotaManager atomic check catches user daily request limits.
     */
    public function test_quotamanager_catches_user_plan_limit(): void
    {
        $manager = app(QuotaManager::class);
        $today = now()->format('Y-m-d');
        $reqKey = "docver:quota:user_{$this->user->id}:date_{$today}:requests";

        // Free plan allows 20 requests
        Cache::put($reqKey, 20);

        $this->expectException(\App\Services\AI\Exceptions\QuotaExceededException::class);
        $this->expectExceptionMessage('Daily user AI request limit reached');

        $manager->checkQuota($this->user->id, $this->workspace->id, 100);
    }

    /**
     * Test 8: Admin usage overview endpoint returns comprehensive quota status.
     */
    public function test_admin_usage_overview_endpoint(): void
    {
        $superAdmin = User::factory()->create([
            'email' => 'superadmin@docver.com',
            'role' => 'super_admin',
        ]);

        $ws = Workspace::create([
            'name' => 'Admin Workspace',
            'slug' => 'admin-ws-' . uniqid(),
            'owner_id' => $superAdmin->id,
            'plan' => 'enterprise',
        ]);
        $ws->members()->attach($superAdmin, ['role' => 'workspace_admin']);

        \Laravel\Sanctum\Sanctum::actingAs($superAdmin, ['*']);

        $response = $this->getJson('/api/v1/admin/ai/usage-overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_requests',
                        'total_tokens',
                        'total_cost_usd',
                        'fallback_events_count',
                    ],
                    'by_provider' => [
                        'gemini',
                        'groq',
                        'openrouter',
                    ],
                    'circuit_breakers',
                    'quota_statuses',
                    'plan_quotas',
                ],
            ]);

        $this->assertTrue($response->json('success'));
    }
}
