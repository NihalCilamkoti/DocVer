<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\Exceptions\QuotaExceededException;
use App\Services\AI\Quota\QuotaManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class QuotaManagerTest extends TestCase
{
    use RefreshDatabase;

    protected QuotaManager $quotaManager;
    protected User $user;
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->quotaManager = new QuotaManager();

        $this->user = User::create([
            'name' => 'Quota Tester',
            'email' => 'quota@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::USER,
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Quota Workspace',
            'slug' => 'quota-ws',
            'owner_id' => $this->user->id,
            'plan' => 'pro',
            'max_file_size_mb' => 50,
        ]);
    }

    public function test_allows_execution_when_within_limits(): void
    {
        // Should not throw
        $this->quotaManager->checkQuota($this->user->id, $this->workspace->id, 5000);
        $this->assertTrue(true);
    }

    public function test_blocks_request_when_daily_user_quota_is_exhausted(): void
    {
        $today = now()->format('Y-m-d');
        // Exceed daily limit
        Cache::put("docver:quota:user_{$this->user->id}:date_{$today}:tokens", 600000);

        $this->expectException(QuotaExceededException::class);
        $this->quotaManager->checkQuota($this->user->id, $this->workspace->id, 1000);
    }

    public function test_records_usage_and_persists_to_database(): void
    {
        $this->quotaManager->recordUsage(
            provider: 'gemini',
            model: 'gemini-2.0-flash',
            inputTokens: 1200,
            outputTokens: 450,
            costUsd: 0.0003,
            durationMs: 420,
            userId: $this->user->id,
            workspaceId: $this->workspace->id,
            success: true
        );

        $this->assertDatabaseHas('usage_records', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'metric_type' => 'ai_tokens',
            'quantity' => 1650,
        ]);
    }
}
