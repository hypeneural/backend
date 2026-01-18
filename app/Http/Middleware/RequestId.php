<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Request ID Middleware
 * 
 * Adds a unique X-Request-Id header to every response.
 * Essential for debugging and observability.
 * 
 * Frontend can send their own X-Request-Id to track requests.
 */
class RequestId
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Use existing request ID from client or generate new one
        $requestId = $request->header('X-Request-Id') ?? Str::uuid()->toString();

        // Store in request for logging
        $request->attributes->set('request_id', $requestId);

        // Process request
        $response = $next($request);

        // Add request ID to response headers
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
