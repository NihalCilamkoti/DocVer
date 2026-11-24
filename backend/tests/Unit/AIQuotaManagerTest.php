<?php

namespace Tests\Unit;

use App\Models\ProviderQuota;
use App\Services\AI\Quota\AIQuotaManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AIQuotaManagerTest extends TestCase
{
    use RefreshDatabase;

    protected AIQuotaManager $quotaManager;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->quotaManager = new AIQuotaManager();
    }

    public function test_allows_execution_when_within_limits(): void
    {
        $canRun = $this->quotaManager->canExecute('gemini', 'gemini-2.0-flash', 1000);
        $this->assertTrue($canRun);
    }

    public function test_blocks_request_when_minute_rpm_exceeded(): void
    {
        $minuteBucket = (int) floor(time() / 60);
        $rpmKey = "docver:quota:gemini:rpm:{$minuteBucket}";
        
        // Gemini default RPM in config is 15
        Cache::put($rpmKey, 15, 60);

        $reason = null;
        $canRun = $this->quotaManager->canExecute('gemini', 'gemini-2.0-flash', 500, $reason);

        $this->assertFalse($canRun);
        $this->assertStringContainsString('RPM limit reached', $reason);
    }

    public function test_blocks_request_when_minute_tpm_exceeded(): void
    {
        $minuteBucket = (int) floor(time() / 60);
        $tpmKey = "docver:quota:groq:tpm:{$minuteBucket}";

        // Groq default TPM in config is 6000
        Cache::put($tpmKey, 5800, 60);

        $reason = null;
        // Requesting 1000 tokens pushes over 6000
        $canRun = $this->quotaManager->canExecute('groq', 'llama-3.1-8b-instant', 1000, $reason);

        $this->assertFalse($canRun);
        $this->assertStringContainsString('TPM limit reached', $reason);
    }

    public function test_proactive_safety_margin_blocks_before_hard_rejection(): void
    {
        $today = Carbon::now('UTC')->format('Y-m-d');

        // Create provider quota in DB with daily limit 1000 and 960 used (4% left, below 5% safety margin)
        ProviderQuota::create([
            'provider_name' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'date' => $today,
            'requests_used_today' => 960,
            'daily_request_limit' => 1000,
            'input_tokens_used_today' => 10000,
            'output_tokens_used_today' => 5000,
            'total_tokens_used_today' => 15000,
            'daily_token_limit' => 1000000,
            'requests_remaining' => 40,
            'tokens_remaining' => 985000,
        ]);

        $reason = null;
        $canRun = $this->quotaManager->canExecute('gemini', 'gemini-2.0-flash', 500, $reason);

        $this->assertFalse($canRun);
        $this->assertStringContainsString('Proactive safety margin', $reason);
    }

    public function test_ingests_response_headers_and_updates_database(): void
    {
        $headers = [
            'requests_limit' => 14400,
            'requests_remaining' => 13500,
            'tokens_limit' => 500000,
            'tokens_remaining' => 480000,
        ];

        $this->quotaManager->recordProviderUsage(
            provider: 'groq',
            model: 'llama-3.3-70b-versatile',
            inputTokens: 120,
            outputTokens: 80,
            headers: $headers
        );

        $today = Carbon::now('UTC')->format('Y-m-d');
        $record = ProviderQuota::where('provider_name', 'groq')
            ->where('date', $today)
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals(1, $record->requests_used_today);
        $this->assertEquals(200, $record->total_tokens_used_today);
        $this->assertEquals(13500, $record->requests_remaining);
        $this->assertEquals(480000, $record->tokens_remaining);
        $this->assertEquals(14400, $record->daily_request_limit);
    }

    public function test_calculates_daily_reset_countdown_accurately(): void
    {
        $status = $this->quotaManager->getProviderQuotaStatus('gemini');

        $this->assertArrayHasKey('reset_countdown_seconds', $status);
        $this->assertGreaterThan(0, $status['reset_countdown_seconds']);
        $this->assertLessThanOrEqual(86400, $status['reset_countdown_seconds']);
    }
}
