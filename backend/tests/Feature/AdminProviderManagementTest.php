<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProviderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $normalUser;
    protected string $adminToken;
    protected string $userToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@docver.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::SUPER_ADMIN,
        ]);

        $this->normalUser = User::create([
            'name' => 'Ordinary Auditor',
            'email' => 'user@docver.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::USER,
        ]);

        $this->adminToken = $this->superAdmin->createToken('admin-token')->plainTextToken;
        $this->userToken = $this->normalUser->createToken('user-token')->plainTextToken;
    }

    public function test_super_admin_can_list_providers_without_exposing_api_keys(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/admin/providers');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'provider',
                        'display_name',
                        'is_configured',
                        'is_enabled',
                        'circuit_breaker_state',
                        'priority_rank',
                        'default_model',
                    ],
                ],
            ]);

        // Strict verification: API key must NEVER be leaked in JSON output
        $content = $response->getContent();
        $this->assertStringNotContainsString('api_key', $content);
        $this->assertStringNotContainsString('AIza', $content);
        $this->assertStringNotContainsString('sk-or', $content);
    }

    public function test_ordinary_user_is_blocked_from_admin_provider_management(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->userToken}")
            ->getJson('/api/v1/admin/providers');

        $response->assertStatus(403);
    }

    public function test_super_admin_can_toggle_provider_status_and_priority(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson('/api/v1/admin/providers/gemini', [
                'is_enabled' => false,
                'reset_circuit_breaker' => true,
                'priority' => 2,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_enabled', false)
            ->assertJsonPath('data.circuit_breaker', 'closed');
    }
}
