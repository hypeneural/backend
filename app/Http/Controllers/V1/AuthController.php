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

/**
 * @group 1. Autenticação
 *
 * APIs para autenticação via OTP (One-Time Password) e gerenciamento de tokens JWT.
 *
 * O fluxo de autenticação funciona assim:
 * 1. Envie o telefone para `/auth/otp/send` - você receberá um código de 6 dígitos via SMS
 * 2. Verifique o código em `/auth/otp/verify` - você receberá os tokens de acesso
 * 3. Use o `access_token` no header Authorization para requisições autenticadas
 * 4. Quando o token expirar, use `/auth/refresh` para obter um novo
 */
class AuthController extends Controller
{
    /**
     * Enviar OTP
     *
     * Envia um código de verificação de 6 dígitos para o telefone informado via SMS.
     * O código expira em 5 minutos e pode ser reenviado após 1 minuto.
     *
     * @unauthenticated
     *
     * @bodyParam phone string required Número de telefone com DDD (sem código do país). Example: 11999999999
     *
     * @response 200 scenario="Código enviado com sucesso" {
     *   "data": {
     *     "message": "Code sent successfully.",
     *     "expires_at": "2026-01-18T05:32:36Z",
     *     "code": "123456"
     *   },
     *   "meta": {"success": true},
     *   "errors": null
     * }
     *
     * @response 429 scenario="Rate limit (aguarde 1 minuto)" {
     *   "data": null,
     *   "meta": {"success": false},
     *   "errors": [{"code": "RATE_LIMIT", "message": "Aguarde 1 minuto para reenviar"}]
     * }
     *
     * @response 422 scenario="Telefone inválido" {
     *   "data": null,
     *   "meta": {"success": false},
     *   "errors": [{"code": "VALIDATION_ERROR", "message": "O campo phone é obrigatório"}]
     * }
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
     * Verificar OTP e Autenticar
     *
     * Valida o código OTP recebido por SMS e retorna os tokens de autenticação.
     * Se o usuário não existir, uma nova conta será criada automaticamente.
     *
     * @unauthenticated
     *
     * @bodyParam phone string required Número de telefone (mesmo usado no envio). Example: 11999999999
     * @bodyParam code string required Código de 6 dígitos recebido por SMS. Example: 123456
     * @bodyParam name string required se novo usuário Nome do usuário (obrigatório apenas para novos usuários). Example: João Silva
     * @bodyParam referral_code string Código de indicação (opcional). Example: BORA123
     *
     * @response 200 scenario="Usuário existente autenticado" {
     *   "data": {
     *     "user": {
     *       "id": "019bcf92-ecda-70a6-98ec-204362b9c61a",
     *       "phone": "+5511999999999",
     *       "name": "João Silva",
     *       "avatar": "https://cdn.example.com/avatar.jpg",
     *       "is_verified": true,
     *       "onboarding_completed": true,
     *       "primary_family_id": "019bcf92-ecde-718f-a588-27021c63eb59",
     *       "primary_city_id": "edbca93c-2f01-4e17-af0a-53b1ccb4bf90"
     *     },
     *     "tokens": {
     *       "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
     *       "refresh_token": "n6rowFjjp07kkFyJ5yKl4vJ4nUvqb6Ry...",
     *       "token_type": "bearer",
     *       "expires_in": 3600
     *     },
     *     "is_new_user": false
     *   },
     *   "meta": {"success": true},
     *   "errors": null
     * }
     *
     * @response 201 scenario="Novo usuário criado" {
     *   "data": {
     *     "user": {
     *       "id": "019bcf92-ecda-70a6-98ec-204362b9c61a",
     *       "phone": "+5511999999999",
     *       "name": "João Silva",
     *       "avatar": null,
     *       "is_verified": true,
     *       "onboarding_completed": false,
     *       "primary_family_id": "019bcf92-ecde-718f-a588-27021c63eb59",
     *       "primary_city_id": null
     *     },
     *     "tokens": {
     *       "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
     *       "refresh_token": "n6rowFjjp07kkFyJ5yKl4vJ4nUvqb6Ry...",
     *       "token_type": "bearer",
     *       "expires_in": 3600
     *     },
     *     "is_new_user": true
     *   },
     *   "meta": {"success": true},
     *   "errors": null
     * }
     *
     * @response 400 scenario="Código expirado" {
     *   "data": null,
     *   "meta": {"success": false},
     *   "errors": [{"code": "OTP_EXPIRED", "message": "Código expirado. Solicite um novo."}]
     * }
     *
     * @response 401 scenario="Código inválido" {
     *   "data": null,
     *   "meta": {"success": false, "attempts_remaining": 2},
     *   "errors": [{"code": "OTP_INVALID", "message": "Código incorreto"}]
     * }
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
     * Renovar Token
     *
     * Gera um novo par de tokens (access + refresh) usando o refresh token atual.
     * O refresh token antigo é invalidado após o uso (one-time use).
     *
     * @unauthenticated
     *
     * @bodyParam refresh_token string required O refresh token obtido no login. Example: n6rowFjjp07kkFyJ5yKl4vJ4nUvqb6Ry...
     *
     * @response 200 scenario="Tokens renovados com sucesso" {
     *   "data": {
     *     "tokens": {
     *       "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
     *       "refresh_token": "novoRefreshToken123...",
     *       "token_type": "bearer",
     *       "expires_in": 3600
     *     }
     *   },
     *   "meta": {"success": true},
     *   "errors": null
     * }
     *
     * @response 401 scenario="Refresh token inválido ou expirado" {
     *   "data": null,
     *   "meta": {"success": false},
     *   "errors": [{"code": "INVALID_REFRESH_TOKEN", "message": "Token inválido ou expirado"}]
     * }
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
     * Logout
     *
     * Revoga os tokens de acesso do usuário. Pode revogar apenas o dispositivo atual
     * ou todos os dispositivos de uma vez.
     *
     * @authenticated
     *
     * @bodyParam refresh_token string O refresh token do dispositivo atual (opcional). Example: n6rowFjjp07kkFyJ5yKl...
     * @bodyParam all_devices boolean Se true, revoga tokens de todos os dispositivos. Default: false. Example: false
     *
     * @response 200 scenario="Logout realizado" {
     *   "data": {"message": "Logged out successfully"},
     *   "meta": {"success": true},
     *   "errors": null
     * }
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
     * Dados do Usuário Logado
     *
     * Retorna os dados completos do usuário autenticado, incluindo estatísticas
     * de gamificação e informações da família principal.
     *
     * @authenticated
     *
     * @response 200 scenario="Dados retornados com sucesso" {
     *   "data": {
     *     "id": "019bcf92-ecda-70a6-98ec-204362b9c61a",
     *     "phone": "+5511999999999",
     *     "name": "João Silva",
     *     "avatar": "https://cdn.example.com/avatar.jpg",
     *     "email": "joao@email.com",
     *     "is_verified": true,
     *     "stats": {
     *       "xp": 500,
     *       "level": 2,
     *       "streak_days": 5
     *     },
     *     "primary_family": {
     *       "id": "019bcf92-ecde-718f-a588-27021c63eb59",
     *       "name": "Família Silva"
     *     },
     *     "created_at": "2026-01-15T10:00:00Z"
     *   },
     *   "meta": {"success": true},
     *   "errors": null
     * }
     *
     * @response 401 scenario="Token inválido" {
     *   "data": null,
     *   "meta": {"success": false},
     *   "errors": [{"code": "UNAUTHENTICATED", "message": "Token inválido ou expirado"}]
     * }
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
