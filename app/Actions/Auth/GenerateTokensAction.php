<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class GenerateTokensAction
{
    /**
     * Generate JWT access token and refresh token
     * 
     * @param User $user
     * @param string|null $deviceName
     * @param string|null $ipAddress
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string}
     */
    public function execute(User $user, ?string $deviceName = null, ?string $ipAddress = null): array
    {
        // Generate JWT access token
        $accessToken = JWTAuth::fromUser($user);
        $expiresIn = config('jwt.ttl', 60) * 60; // Convert minutes to seconds

        // Generate refresh token
        $rawRefreshToken = Str::random(64);

        $refreshToken = RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $rawRefreshToken),
            'expires_at' => now()->addDays(config('jwt.refresh_ttl', 20160) / 1440), // Convert minutes to days
            'device_name' => $deviceName,
            'ip_address' => $ipAddress,
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $rawRefreshToken,
            'expires_in' => $expiresIn,
            'token_type' => 'Bearer',
        ];
    }
}
