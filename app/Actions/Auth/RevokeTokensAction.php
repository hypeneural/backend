<?php

namespace App\Actions\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class RevokeTokensAction
{
    /**
     * Revoke all tokens for user (logout)
     * 
     * @param User $user
     * @param string|null $refreshToken
     * @param bool $allDevices
     * @return array{success: bool, message: string}
     */
    public function execute(User $user, ?string $refreshToken = null, bool $allDevices = false): array
    {
        // Invalidate JWT token
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (\Exception $e) {
            // Token might already be invalid
        }

        if ($allDevices) {
            // Revoke all refresh tokens
            RefreshToken::where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return [
                'success' => true,
                'message' => 'Logged out from all devices.',
            ];
        }

        // Revoke only provided refresh token
        if ($refreshToken) {
            RefreshToken::where('token_hash', hash('sha256', $refreshToken))
                ->where('user_id', $user->id)
                ->update(['revoked_at' => now()]);
        }

        return [
            'success' => true,
            'message' => 'Logged out successfully.',
        ];
    }
}
