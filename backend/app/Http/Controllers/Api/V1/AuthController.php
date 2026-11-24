<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\LoginDTO;
use App\DTOs\RegisterDTO;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class AuthController extends BaseApiController
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $dto = RegisterDTO::fromArray($request->validated());
        $result = $this->authService->register($dto);

        return $this->successResponse([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'workspace' => $result['workspace'],
        ], 'Registration successful', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $dto = LoginDTO::fromArray($request->validated());
        $result = $this->authService->login($dto);

        return $this->successResponse([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'workspace' => $result['workspace'],
        ], 'Authentication successful');
    }

    public function logout(Request $request): JsonResponse
    {
        $allDevices = $request->boolean('all_devices', false);
        $this->authService->logout($request->user(), $allDevices);

        return $this->successResponse([], 'Logged out successfully');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            new UserResource($request->user()->load('workspaces'))
        );
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->authService->updateProfile($request->user(), $request->validated());

        return $this->successResponse(
            new UserResource($user),
            'Profile updated successfully'
        );
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password')
        );

        return $this->successResponse([], 'Password changed successfully');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        // Enforce anti-enumeration: always return success
        $user = User::where('email', $request->validated('email'))->first();
        if ($user) {
            // Password broker or token logic
            Password::broker()->sendResetLink($request->only('email'));
        }

        return $this->successResponse(
            [],
            'If this email is registered in DocVer, a password reset link has been dispatched.'
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->password = $password;
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->successResponse([], 'Password reset successfully. You may now login.');
        }

        return $this->errorResponse(__($status), [], 400);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->successResponse([], 'Email is already verified.');
        }

        $request->user()->markEmailAsVerified();

        return $this->successResponse([], 'Email verified successfully.');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->errorResponse('Email already verified.', [], 400);
        }

        $request->user()->sendEmailVerificationNotification();

        return $this->successResponse([], 'Verification email link re-sent.');
    }
}
