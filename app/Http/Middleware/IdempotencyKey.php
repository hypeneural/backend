<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency Key Middleware
 * 
 * Prevents duplicate operations for POST/PUT/DELETE requests.
 * Frontend should send: Idempotency-Key: {uuid}
 * 
 * Usage: Apply to sensitive endpoints that should not be duplicated
 * (favorites, reviews, plans, memories, etc.)
 */
class IdempotencyKey
{
    /**
     * Cache TTL in seconds (10 minutes)
     */
    protected int $ttl = 600;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to state-changing methods
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        // If no key provided, proceed normally
        if (!$idempotencyKey) {
            return $next($request);
        }

        // Validate key format (UUID)
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $idempotencyKey)) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['code' => 'INVALID_IDEMPOTENCY_KEY', 'message' => 'Idempotency-Key must be a valid UUID']],
            ], 400);
        }

        // Create cache key with user context
        $userId = $request->user()?->id ?? 'anonymous';
        $cacheKey = "idempotency:{$userId}:{$idempotencyKey}";

        // Check if we have a cached response
        $cachedResponse = Cache::get($cacheKey);

        if ($cachedResponse) {
            // Return the cached response with indicator header
            return response()
                ->json($cachedResponse['body'], $cachedResponse['status'])
                ->header('X-Idempotency-Replayed', 'true');
        }

        // Process the request
        $response = $next($request);

        // Only cache successful responses (2xx)
        if ($response->isSuccessful()) {
            Cache::put($cacheKey, [
                'body' => json_decode($response->getContent(), true),
                'status' => $response->getStatusCode(),
            ], $this->ttl);
        }

        return $response;
    }
}
