<?php

/**
 * Rate Limiting Configuration
 * 
 * Defines refined rate limits per endpoint type.
 * Used by RouteServiceProvider or bootstrap/app.php
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Global Rate Limits
    |--------------------------------------------------------------------------
    */
    'global' => [
        'requests_per_minute' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Rate Limits (Sensitive - Prevent Brute Force)
    |--------------------------------------------------------------------------
    */
    'auth' => [
        // OTP send: 5 attempts per phone per 15 minutes
        'otp_send' => [
            'max_attempts' => 5,
            'decay_minutes' => 15,
            'by' => 'phone', // Rate limit by phone number
        ],

        // OTP verify: 10 attempts per phone per 15 minutes
        'otp_verify' => [
            'max_attempts' => 10,
            'decay_minutes' => 15,
            'by' => 'phone',
        ],

        // Token refresh: 20 per hour
        'refresh' => [
            'max_attempts' => 20,
            'decay_minutes' => 60,
            'by' => 'user',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deep Link / Invite Codes (Prevent Brute Force)
    |--------------------------------------------------------------------------
    */
    'resolve' => [
        // Resolve codes: 30 attempts per IP per minute
        'max_attempts' => 30,
        'decay_minutes' => 1,
        'by' => 'ip',
    ],

    /*
    |--------------------------------------------------------------------------
    | Read Operations (Higher Limits)
    |--------------------------------------------------------------------------
    */
    'read' => [
        // Search: 60 per minute
        'search' => [
            'max_attempts' => 60,
            'decay_minutes' => 1,
            'by' => 'user_or_ip',
        ],

        // Home feed: 30 per minute (cached anyway)
        'home' => [
            'max_attempts' => 30,
            'decay_minutes' => 1,
            'by' => 'user',
        ],

        // Map: 60 per minute (heavy endpoint)
        'map' => [
            'max_attempts' => 60,
            'decay_minutes' => 1,
            'by' => 'user_or_ip',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Write Operations (Lower Limits)
    |--------------------------------------------------------------------------
    */
    'write' => [
        // Reviews: 10 per hour
        'reviews' => [
            'max_attempts' => 10,
            'decay_minutes' => 60,
            'by' => 'user',
        ],

        // Memories: 20 per hour
        'memories' => [
            'max_attempts' => 20,
            'decay_minutes' => 60,
            'by' => 'user',
        ],

        // Plans: 30 per hour
        'plans' => [
            'max_attempts' => 30,
            'decay_minutes' => 60,
            'by' => 'user',
        ],

        // Favorites: 100 per hour (toggle action)
        'favorites' => [
            'max_attempts' => 100,
            'decay_minutes' => 60,
            'by' => 'user',
        ],

        // Reports: 5 per hour
        'reports' => [
            'max_attempts' => 5,
            'decay_minutes' => 60,
            'by' => 'user',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads (Expensive Operations)
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        // Presign URLs: 30 per hour
        'presign' => [
            'max_attempts' => 30,
            'decay_minutes' => 60,
            'by' => 'user',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Family Operations
    |--------------------------------------------------------------------------
    */
    'family' => [
        // Invite generation: 10 per hour
        'invite' => [
            'max_attempts' => 10,
            'decay_minutes' => 60,
            'by' => 'user',
        ],

        // Join family: 5 per hour
        'join' => [
            'max_attempts' => 5,
            'decay_minutes' => 60,
            'by' => 'user',
        ],
    ],
];
