<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        //
    })->create();

