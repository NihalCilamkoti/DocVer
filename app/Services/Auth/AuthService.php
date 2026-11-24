<?php

namespace App\Services\Auth;

use App\DTOs\LoginDTO;
use App\DTOs\RegisterDTO;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Audit\AuditService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function register(RegisterDTO $dto): array
    {
        $user = User::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => Hash::make($dto->password),
            'role' => UserRole::WORKSPACE_OWNER,
            'is_active' => true,
        ]);

        // Auto-provision initial workspace
        $slug = Str::slug($dto->organization) . '-' . Str::random(5);
        $workspace = Workspace::create([
            'name' => $dto->organization,
            'slug' => $slug,
            'owner_id' => $user->id,
            'plan' => 'professional',
            'status' => 'active',
            'max_file_size_mb' => 50,
            'max_page_limit' => 500,
            'retention_days' => 30,
        ]);

        $workspace->members()->attach($user->id, ['role' => 'owner']);

        event(new Registered($user));

        $this->auditService->log('auth.registered', $workspace->id, $user->id, [
            'email' => $user->email,
            'organization' => $dto->organization,
        ]);

        $token = $user->createToken('docver-browser-token', ['*'], now()->addDays(30))->plainTextToken;

        return [
            'user' => $user->fresh(['workspaces']),
            'token' => $token,
            'workspace' => $workspace,
        ];
    }

    public function login(LoginDTO $dto): array
    {
        $user = User::where('email', $dto->email)->first();

        if (! $user || ! Hash::check($dto->password, $user->password)) {
            // Mitigate enumeration and log failed attempt
            $this->auditService->log('auth.login_failed', null, null, [
                'attempted_email' => $dto->email,
            ]);

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your enterprise account has been deactivated. Contact your administrator.'],
            ]);
        }

        $user->update(['last_login_at' => now()]);

        $tokenName = $dto->device ? "docver-device-{$dto->device}" : 'docver-session-token';
        $expiresAt = $dto->remember ? now()->addDays(30) : now()->addHours(12);

        $token = $user->createToken($tokenName, ['*'], $expiresAt)->plainTextToken;

        $primaryWorkspace = $user->workspaces()->first() ?? $user->ownedWorkspaces()->first();

        $this->auditService->log('auth.login', $primaryWorkspace?->id, $user->id, [
            'device' => $dto->device,
            'remember' => $dto->remember,
        ]);

        return [
            'user' => $user->load('workspaces'),
            'token' => $token,
            'workspace' => $primaryWorkspace,
        ];
    }

    public function logout(User $user, bool $allDevices = false): void
    {
        if ($allDevices) {
            $user->tokens()->delete();
        } else {
            $user->currentAccessToken()?->delete();
        }

        $this->auditService->log('auth.logout', null, $user->id, [
            'all_devices' => $allDevices,
        ]);
    }

    public function updateProfile(User $user, array $data): User
    {
        $user->update([
            'name' => $data['name'] ?? $user->name,
            'phone' => $data['phone'] ?? $user->phone,
            'avatar_url' => $data['avatar_url'] ?? $user->avatar_url,
        ]);

        $this->auditService->log('user.profile_updated', null, $user->id);

        return $user;
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password supplied is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        // Revoke existing tokens for security, except current one if specified
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();

        $this->auditService->log('auth.password_changed', null, $user->id);
    }
}
