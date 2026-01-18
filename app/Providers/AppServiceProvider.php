<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
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
        // OTP send: 1 request per minute per phone/IP
        RateLimiter::for('otp', function (Request $request) {
            $phone = $request->input('phone', 'unknown');
            $ip = $request->ip();

            return [
                Limit::perMinute(1)->by('phone:' . $phone),
                Limit::perMinute(5)->by('ip:' . $ip),
            ];
        });

        // OTP verify: 5 attempts per 5 minutes
        RateLimiter::for('otp_verify', function (Request $request) {
            $phone = $request->input('phone', 'unknown');

            return Limit::perMinutes(5, 5)->by('otp_verify:' . $phone);
        });

        // API general: 60 requests per minute
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Search: 30 requests per minute
        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Write operations: 20 per minute
        RateLimiter::for('writes', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });
    }
}
