<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Auth\AuthAuditService;
use App\Application\Auth\AuthCookieService;
use App\Application\Auth\AuthService;
use App\Application\Auth\PasswordResetService;
use App\Application\Auth\PhoneVerificationService;
use App\Application\Auth\RefreshTokenService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyPhoneRequest;
use App\Http\Resources\AuthUserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly AuthCookieService $authCookieService,
        private readonly PhoneVerificationService $phoneVerificationService,
        private readonly PasswordResetService $passwordResetService,
        private readonly AuthAuditService $authAudit,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());
        $user->load('roles');

        return ApiResponse::success(
            data: [
                'user' => new AuthUserResource($user),
            ],
            message: 'Registration completed successfully.',
            status: Response::HTTP_CREATED,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->authenticate(
            $request->string('identifier')->toString(),
            $request->string('password')->toString(),
        );

        return $this->tokenResponse($user, 'Login completed successfully.');
    }

    public function refresh(Request $request): JsonResponse
    {
        $plainRefresh = (string) $request->cookie($this->authCookieService->refreshCookieName());

        $result = $this->refreshTokenService->rotate($plainRefresh);

        return $this->tokenResponse(
            $result['user'],
            'Token refreshed successfully.',
            $result['refresh_token'],
            $result['access_token'],
        );
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user?->load('roles');

        return ApiResponse::success(
            data: [
                'user' => new AuthUserResource($user),
            ],
            message: 'Authenticated session retrieved successfully.',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $plainRefresh = (string) $request->cookie($this->authCookieService->refreshCookieName());

        $this->refreshTokenService->revokeByPlainToken($plainRefresh);

        if ($user !== null) {
            $this->authAudit->logout($user);
        }

        auth('api')->logout();

        return $this->clearAuthCookies(
            ApiResponse::success(message: 'Logout completed successfully.'),
        );
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->refreshTokenService->revokeAllForUser($user);
        $this->authAudit->logoutAll($user);
        $this->authAudit->tokensRevoked($user, 'logout_all');

        auth('api')->logout();

        return $this->clearAuthCookies(
            ApiResponse::success(message: 'All sessions have been revoked.'),
        );
    }

    public function verifyPhone(VerifyPhoneRequest $request): JsonResponse
    {
        $user = $this->phoneVerificationService->verify(
            $request->user(),
            $request->string('code')->toString(),
        );

        $user->load('roles');

        return ApiResponse::success(
            data: ['user' => new AuthUserResource($user)],
            message: 'Phone number verified successfully.',
        );
    }

    public function resendPhoneCode(Request $request): JsonResponse
    {
        $this->phoneVerificationService->send($request->user());

        return ApiResponse::success(message: 'Verification code sent successfully.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->requestReset($request->string('identifier')->toString());

        return ApiResponse::success(
            message: 'If an account matches the provided details, a verification code will be sent.',
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->resetPassword(
            $request->string('identifier')->toString(),
            $request->string('code')->toString(),
            $request->string('password')->toString(),
        );

        return ApiResponse::success(message: 'Password reset completed successfully.');
    }

    private function tokenResponse(
        $user,
        string $message,
        ?string $refreshToken = null,
        ?string $accessToken = null,
    ): JsonResponse {
        $user->load('roles');

        $issuedRefresh = $refreshToken ?? $this->refreshTokenService->issue($user)['token'];
        $accessToken ??= $this->refreshTokenService->createAccessToken($user);
        $csrfToken = $this->authCookieService->createCsrfToken();

        return ApiResponse::success(
            data: [
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => (int) config('jwt.ttl') * 60,
                'user' => new AuthUserResource($user),
            ],
            message: $message,
        )->withCookie($this->authCookieService->makeRefreshCookie($issuedRefresh))
            ->withCookie($this->authCookieService->makeCsrfCookie($csrfToken));
    }

    private function clearAuthCookies(JsonResponse $response): JsonResponse
    {
        foreach ($this->authCookieService->forgetAuthCookies() as $cookie) {
            $response = $response->withCookie($cookie);
        }

        return $response;
    }
}
