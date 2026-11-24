<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_workspace_is_provisioned(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Eleanor Vance',
            'email' => 'eleanor@example.com',
            'password' => 'SecurePass123',
            'organization' => 'Vance Legal Partners',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'eleanor@example.com')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'role'],
                    'token',
                    'workspace' => ['id', 'name', 'slug'],
                ],
            ]);

        $this->assertDatabaseHas('users', ['email' => 'eleanor@example.com']);
        $this->assertDatabaseHas('workspaces', ['name' => 'Vance Legal Partners']);
    }

    public function test_registration_validation_fails_with_standard_error_response(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'short',
            'organization' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['name', 'email', 'password', 'organization'],
            ]);
    }

    public function test_user_can_login_and_receive_sanctum_token(): void
    {
        $user = User::create([
            'name' => 'Alex Sterling',
            'email' => 'alex@sterling.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::WORKSPACE_OWNER,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'alex@sterling.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['user', 'token'],
            ]);
    }

    public function test_login_fails_with_invalid_credentials_preventing_enumeration(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed');
    }

    public function test_authenticated_user_can_access_me_and_logout(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::USER,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // View profile
        $meResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/user');

        $meResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'test@example.com');

        // Logout
        $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verify token deleted from database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_unauthenticated_request_is_blocked_with_standard_401(): void
    {
        $response = $this->getJson('/api/v1/user');

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated or session expired.');
    }
}
