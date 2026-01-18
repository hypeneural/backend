<?php

namespace App\Http\Controllers\V1;

use App\Actions\Auth\GenerateTokensAction;
use App\Actions\Auth\RefreshTokensAction;
use App\Actions\Auth\RevokeTokensAction;
use App\Actions\Auth\SendOtpAction;
use App\Actions\Auth\VerifyOtpAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Send OTP to phone number
     * POST /v1/auth/otp/send
     */
    public function sendOtp(SendOtpRequest $request, SendOtpAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->phone,
            $request->ip(),
            $request->header('X-Device-Fingerprint')
        );

        if (!$result['success']) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => $result['message']]],
            ], 429);
        }

        $responseData = [
            'message' => $result['message'],
            'expires_at' => $result['expires_at']?->toISOString(),
        ];

        // Include code in development for testing
        if (isset($result['code'])) {
            $responseData['code'] = $result['code'];
        }

        return response()->json([
            'data' => $responseData,
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Verify OTP and authenticate
     * POST /v1/auth/otp/verify
     */
    public function verifyOtp(
        VerifyOtpRequest $request,
        VerifyOtpAction $verifyAction,
        GenerateTokensAction $generateTokensAction
    ): JsonResponse {
        $result = $verifyAction->execute(
            $request->phone,
            $request->code,
            $request->name,
            $request->referral_code
        );

        if (!$result['success']) {
            $statusCode = isset($result['attempts_remaining']) ? 401 : 400;

            return response()->json([
                'data' => null,
                'meta' => [
                    'success' => false,
                    'attempts_remaining' => $result['attempts_remaining'] ?? null,
                ],
                'errors' => [['message' => $result['message']]],
            ], $statusCode);
        }

        // Generate tokens
        $tokens = $generateTokensAction->execute(
            $result['user'],
            $request->header('X-Device-Name'),
            $request->ip()
        );

        $user = $result['user'];
        $primaryFamily = $user->getPrimaryFamily();

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'phone' => $user->phone,
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                    'is_verified' => $user->is_verified,
                    'onboarding_completed' => $user->onboarding_completed ?? false,
                    'primary_family_id' => $primaryFamily?->id,
                    'primary_city_id' => $user->primary_city_id,
                ],
                'tokens' => $tokens,
                'is_new_user' => $result['is_new_user'],
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ], $result['is_new_user'] ? 201 : 200);
    }

    /**
     * Refresh access token
     * POST /v1/auth/refresh
     */
    public function refresh(RefreshTokenRequest $request, RefreshTokensAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->refresh_token,
            $request->header('X-Device-Name'),
            $request->ip()
        );

        if (!$result['success']) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => $result['message']]],
            ], 401);
        }

        return response()->json([
            'data' => ['tokens' => $result['tokens']],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Logout (revoke tokens)
     * POST /v1/auth/logout
     */
    public function logout(Request $request, RevokeTokensAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->user(),
            $request->input('refresh_token'),
            $request->boolean('all_devices', false)
        );

        return response()->json([
            'data' => ['message' => $result['message']],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Get current user
     * GET /v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['stats', 'families']);

        $primaryFamily = $user->getPrimaryFamily();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'name' => $user->name,
                'avatar' => $user->avatar,
                'email' => $user->email,
                'is_verified' => $user->is_verified,
                'stats' => $user->stats ? [
                    'xp' => $user->stats->xp,
                    'level' => $user->stats->level,
                    'streak_days' => $user->stats->streak_days,
                ] : null,
                'primary_family' => $primaryFamily ? [
                    'id' => $primaryFamily->id,
                    'name' => $primaryFamily->name,
                ] : null,
                'created_at' => $user->created_at?->toISOString(),
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }
}
