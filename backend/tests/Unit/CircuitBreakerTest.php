<?php

namespace Tests\Unit;

use App\Services\AI\Health\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    protected CircuitBreaker $circuitBreaker;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->circuitBreaker = new CircuitBreaker(failureThreshold: 3, cooldownSeconds: 10, halfOpenSuccessThreshold: 2);
    }

    public function test_initial_state_is_closed_and_available(): void
    {
        $this->assertEquals(CircuitBreaker::STATE_CLOSED, $this->circuitBreaker->getState('gemini'));
        $this->assertTrue($this->circuitBreaker->isAvailable('gemini'));
    }

    public function test_trips_to_open_after_failure_threshold_reached(): void
    {
        $this->circuitBreaker->recordFailure('gemini');
        $this->circuitBreaker->recordFailure('gemini');
        $this->assertEquals(CircuitBreaker::STATE_CLOSED, $this->circuitBreaker->getState('gemini'));

        // 3rd failure trips the breaker
        $this->circuitBreaker->recordFailure('gemini');
        $this->assertEquals(CircuitBreaker::STATE_OPEN, $this->circuitBreaker->getState('gemini'));
        $this->assertFalse($this->circuitBreaker->isAvailable('gemini'));
    }

    public function test_manual_reset_restores_closed_state(): void
    {
        $this->circuitBreaker->recordFailure('gemini');
        $this->circuitBreaker->recordFailure('gemini');
        $this->circuitBreaker->recordFailure('gemini');
        $this->assertEquals(CircuitBreaker::STATE_OPEN, $this->circuitBreaker->getState('gemini'));

        $this->circuitBreaker->reset('gemini');
        $this->assertEquals(CircuitBreaker::STATE_CLOSED, $this->circuitBreaker->getState('gemini'));
        $this->assertTrue($this->circuitBreaker->isAvailable('gemini'));
    }

    public function test_success_resets_failure_count(): void
    {
        $this->circuitBreaker->recordFailure('gemini');
        $this->circuitBreaker->recordFailure('gemini');
        $this->circuitBreaker->recordSuccess('gemini');

        // Next failure should be attempt 1, not 3
        $this->circuitBreaker->recordFailure('gemini');
        $this->assertEquals(CircuitBreaker::STATE_CLOSED, $this->circuitBreaker->getState('gemini'));
    }
}
