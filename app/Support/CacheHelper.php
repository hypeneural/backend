<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Cache and Redis helper with graceful fallbacks.
 * Works with any cache driver (file, array, redis, memcached).
 */
class CacheHelper
{
    /**
     * Check if current cache driver supports tagging.
     */
    public static function supportsTagging(): bool
    {
        $driver = config('cache.default');
        return in_array($driver, ['redis', 'memcached', 'dynamodb']);
    }

    /**
     * Cache remember with optional tags (silently falls back if tags not supported).
     */
    public static function remember(string $key, int $ttlSeconds, callable $callback, array $tags = []): mixed
    {
        try {
            if (!empty($tags) && self::supportsTagging()) {
                return Cache::tags($tags)->remember($key, $ttlSeconds, $callback);
            }
            return Cache::remember($key, $ttlSeconds, $callback);
        } catch (\Throwable $e) {
            Log::warning('Cache remember failed, executing callback directly', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return $callback();
        }
    }

    /**
     * Flush cache with optional tags (silently ignores if tags not supported).
     */
    public static function flushTags(array $tags): void
    {
        try {
            if (!empty($tags) && self::supportsTagging()) {
                Cache::tags($tags)->flush();
            }
        } catch (\Throwable $e) {
            Log::debug('Cache tag flush failed (expected if not using redis/memcached)', [
                'tags' => $tags,
            ]);
        }
    }

    /**
     * Forget a specific cache key.
     */
    public static function forget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (\Throwable $e) {
            Log::debug('Cache forget failed', ['key' => $key]);
        }
    }

    /**
     * Redis increment with fallback (silent failure if Redis unavailable).
     */
    public static function redisIncrement(string $hash, string $field, int $value = 1): void
    {
        try {
            if (self::isRedisAvailable()) {
                Redis::hincrby($hash, $field, $value);
            }
        } catch (\Throwable $e) {
            Log::debug('Redis increment failed (not critical)', [
                'hash' => $hash,
                'field' => $field,
            ]);
        }
    }

    /**
     * Redis set add with fallback.
     */
    public static function redisSetAdd(string $key, string $member): void
    {
        try {
            if (self::isRedisAvailable()) {
                Redis::sadd($key, $member);
            }
        } catch (\Throwable $e) {
            Log::debug('Redis sadd failed (not critical)', ['key' => $key]);
        }
    }

    /**
     * Check if Redis is available.
     */
    public static function isRedisAvailable(): bool
    {
        try {
            return config('database.redis.client') !== 'phpredis' || Redis::ping();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
