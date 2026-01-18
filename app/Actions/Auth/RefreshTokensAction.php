<?php

namespace App\Actions\Auth;

use App\Models\RefreshToken;

class RefreshTokensAction
{
    protected GenerateTokensAction $generateTokensAction;

    public function __construct(GenerateTokensAction $generateTokensAction)
    {
        $this->generateTokensAction = $generateTokensAction;
    }

    /**
     * Refresh tokens with rotation (invalidate old refresh token)
     * 
     * @param string $rawRefreshToken
     * @param string|null $deviceName
     * @param string|null $ipAddress
     * @return array{success: bool, message?: string, tokens?: array}
     */
    public function execute(string $rawRefreshToken, ?string $deviceName = null, ?string $ipAddress = null): array
    {
        $tokenHash = hash('sha256', $rawRefreshToken);

        $refreshToken = RefreshToken::where('token_hash', $tokenHash)
            ->with('user')
            ->first();

        if (!$refreshToken) {
            return [
                'success' => false,
                'message' => 'Invalid refresh token.',
            ];
        }

        if (!$refreshToken->isValid()) {
            // Token was revoked or expired - potential token reuse attack
            // Revoke all tokens for this user as a security measure
            RefreshToken::where('user_id', $refreshToken->user_id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return [
                'success' => false,
                'message' => 'Refresh token expired or revoked.',
            ];
        }

        // Revoke the old refresh token (rotation)
        $refreshToken->revoke();

        // Generate new tokens
        $tokens = $this->generateTokensAction->execute(
            $refreshToken->user,
            $deviceName ?? $refreshToken->device_name,
            $ipAddress ?? $refreshToken->ip_address
        );

        // Link to rotated token for audit
        RefreshToken::where('token_hash', hash('sha256', $tokens['refresh_token']))
            ->update(['rotated_from_id' => $refreshToken->id]);

        return [
            'success' => true,
            'tokens' => $tokens,
        ];
    }
}
