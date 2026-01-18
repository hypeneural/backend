<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware - runs on every request
        $middleware->api(prepend: [
            \App\Http\Middleware\RequestId::class,
        ]);

        // Middleware aliases
        $middleware->alias([
            'auth' => \PHPOpenSourceSaver\JWTAuth\Http\Middleware\Authenticate::class,
            'idempotent' => \App\Http\Middleware\IdempotencyKey::class,
        ]);

        $middleware->throttleApi('60,1');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Only render custom JSON for API requests
        $exceptions->render(function (Throwable $e, Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null; // Use default rendering for non-API
            }

            // JWT Token Expired
            if ($e instanceof TokenExpiredException) {
                return response()->json([
                    'data' => null,
                    'meta' => ['success' => false],
                    'errors' => [
                        [
                            'code' => 'TOKEN_EXPIRED',
                            'message' => 'Token expirado. Faça login novamente.',
                        ]
                    ],
                ], 401);
            }

            // JWT Token Invalid
            if ($e instanceof TokenInvalidException) {
                return response()->json([
                    'data' => null,
                    'meta' => ['success' => false],
                    'errors' => [
                        [
                            'code' => 'TOKEN_INVALID',
                            'message' => 'Token inválido.',
                        ]
                    ],
                ], 401);
            }

            // JWT General Error (missing token, etc)
            if ($e instanceof JWTException) {
                return response()->json([
                    'data' => null,
                    'meta' => ['success' => false],
                    'errors' => [
                        [
                            'code' => 'TOKEN_ERROR',
                            'message' => 'Problema com autenticação. Token ausente ou inválido.',
                        ]
                    ],
                ], 401);
            }

            // Authentication Error
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'data' => null,
                    'meta' => ['success' => false],
                    'errors' => [
                        [
                            'code' => 'UNAUTHENTICATED',
                            'message' => 'Não autenticado. Faça login.',
                        ]
                    ],
                ], 401);
            }

            // Validation Error
            if ($e instanceof ValidationException) {
                return response()->json([
                    'data' => null,
                    'meta' => ['success' => false],
                    'errors' => collect($e->errors())->flatten()->map(fn($msg) => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => $msg,
                    ])->values()->toArray(),
                ], 422);
            }

            // Model Not Found
            if ($e instanceof ModelNotFoundException) {
                $model = class_basename($e->getModel());
                return response()->json([
                    'data' => null,
                    'meta' => ['success' => false],
                    'errors' => [
                        [
                            'code' => 'NOT_FOUND',
                            'message' => "{$model} não encontrado.",
                        ]
                    ],
                ], 404);
            }

            // Route Not Found
            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    'data' => null,
                    'meta' => ['success' => false],
                    'errors' => [
                        [
                            'code' => 'NOT_FOUND',
                            'message' => 'Endpoint não encontrado.',
                        ]
                    ],
                ], 404);
            }

            // Method Not Allowed
            if ($e instanceof MethodNotAllowedHttpException) {
                return response()->json([
                    'data' => null,
                    'meta' => ['success' => false],
                    'errors' => [
                        [
                            'code' => 'METHOD_NOT_ALLOWED',
                            'message' => 'Método HTTP não permitido para esta rota.',
                        ]
                    ],
                ], 405);
            }

            // Throttle / Rate Limit
            if ($e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
                return response()->json([
                    'data' => null,
                    'meta' => ['success' => false],
                    'errors' => [
                        [
                            'code' => 'RATE_LIMITED',
                            'message' => 'Muitas requisições. Tente novamente em alguns minutos.',
                        ]
                    ],
                ], 429);
            }

            // Generic 500 - log and return friendly message
            if (app()->environment('production')) {
                report($e); // Log error
    
                return response()->json([
                    'data' => null,
                    'meta' => ['success' => false],
                    'errors' => [
                        [
                            'code' => 'INTERNAL_ERROR',
                            'message' => 'Erro interno do servidor. Tente novamente.',
                        ]
                    ],
                ], 500);
            }

            // In development, show full error
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [
                    [
                        'code' => 'INTERNAL_ERROR',
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => collect($e->getTrace())->take(5)->toArray(),
                    ]
                ],
            ], 500);
        });
    })->create();


