<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimitServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // OTP Send - 5 per 15 minutes per phone
        RateLimiter::for('otp', function (Request $request) {
            $phone = $request->input('phone', 'unknown');
            return Limit::perMinutes(15, 5)
                ->by($phone)
                ->response(function () {
                    return response()->json([
                        'data' => null,
                        'meta' => ['success' => false],
                        'errors' => [
                            [
                                'code' => 'RATE_LIMITED',
                                'message' => 'Muitas tentativas. Aguarde 15 minutos.',
                            ]
                        ],
                    ], 429);
                });
        });

        // OTP Verify - 10 per 15 minutes per phone
        RateLimiter::for('otp_verify', function (Request $request) {
            $phone = $request->input('phone', 'unknown');
            return Limit::perMinutes(15, 10)
                ->by($phone)
                ->response(function () {
                    return response()->json([
                        'data' => null,
                        'meta' => ['success' => false],
                        'errors' => [
                            [
                                'code' => 'RATE_LIMITED',
                                'message' => 'Muitas tentativas de verificação. Aguarde 15 minutos.',
                            ]
                        ],
                    ], 429);
                });
        });

        // Deep link resolve - 30 per minute per IP
        RateLimiter::for('resolve', function (Request $request) {
            return Limit::perMinute(30)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'data' => null,
                        'meta' => ['success' => false],
                        'errors' => [
                            [
                                'code' => 'RATE_LIMITED',
                                'message' => 'Muitas requisições. Tente novamente em 1 minuto.',
                            ]
                        ],
                    ], 429);
                });
        });

        // Search - 60 per minute
        RateLimiter::for('search', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();
            return Limit::perMinute(60)->by($key);
        });

        // Map - 60 per minute
        RateLimiter::for('map', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();
            return Limit::perMinute(60)->by($key);
        });

        // Write operations - 30 per hour
        RateLimiter::for('write', function (Request $request) {
            return Limit::perHour(30)->by($request->user()?->id ?? $request->ip());
        });

        // Reviews - 10 per hour
        RateLimiter::for('reviews', function (Request $request) {
            return Limit::perHour(10)
                ->by($request->user()?->id)
                ->response(function () {
                    return response()->json([
                        'data' => null,
                        'meta' => ['success' => false],
                        'errors' => [
                            [
                                'code' => 'RATE_LIMITED',
                                'message' => 'Limite de reviews atingido. Tente novamente em 1 hora.',
                            ]
                        ],
                    ], 429);
                });
        });

        // Uploads - 30 per hour
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perHour(30)->by($request->user()?->id);
        });

        // Reports - 5 per hour (abuse prevention)
        RateLimiter::for('reports', function (Request $request) {
            return Limit::perHour(5)
                ->by($request->user()?->id)
                ->response(function () {
                    return response()->json([
                        'data' => null,
                        'meta' => ['success' => false],
                        'errors' => [
                            [
                                'code' => 'RATE_LIMITED',
                                'message' => 'Limite de denúncias atingido.',
                            ]
                        ],
                    ], 429);
                });
        });

        // Family invite - 10 per hour
        RateLimiter::for('family_invite', function (Request $request) {
            return Limit::perHour(10)->by($request->user()?->id);
        });
    }
}
