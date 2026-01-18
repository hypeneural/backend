<?php

namespace App\Services;

use App\Models\DeviceSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Device Session Service
 * 
 * Manages device sessions for:
 * - Multi-device login tracking
 * - Push token management
 * - "Logout from all devices"
 * - Abuse detection
 */
class DeviceSessionService
{
    /**
     * Create or update device session on login
     */
    public function registerDevice(
        User $user,
        Request $request,
        ?string $deviceId = null,
        ?string $deviceName = null,
        ?string $deviceType = null,
        ?string $pushToken = null
    ): DeviceSession {
        // Find existing session by device_id or create new
        $session = DeviceSession::where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->first();

        if ($session) {
            // Update existing session
            $session->update([
                'push_token' => $pushToken ?? $session->push_token,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'last_active_at' => now(),
                'is_active' => true,
                'revoked_at' => null,
            ]);
        } else {
            // Create new session
            $session = DeviceSession::create([
                'id' => Str::uuid()->toString(),
                'user_id' => $user->id,
                'device_id' => $deviceId ?? Str::uuid()->toString(),
                'device_name' => $deviceName ?? $this->detectDeviceName($request),
                'device_type' => $deviceType ?? $this->detectDeviceType($request),
                'os_version' => $this->detectOsVersion($request),
                'app_version' => $request->header('X-App-Version'),
                'push_token' => $pushToken,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'last_active_at' => now(),
                'is_active' => true,
            ]);
        }

        return $session;
    }

    /**
     * Update push token for a device
     */
    public function updatePushToken(string $sessionId, string $pushToken): bool
    {
        return DeviceSession::where('id', $sessionId)
            ->update([
                'push_token' => $pushToken,
                'updated_at' => now(),
            ]) > 0;
    }

    /**
     * Get all active sessions for a user
     */
    public function getActiveSessions(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return DeviceSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('last_active_at', 'desc')
            ->get();
    }

    /**
     * Revoke a specific session
     */
    public function revokeSession(string $sessionId): bool
    {
        return DeviceSession::where('id', $sessionId)
            ->update([
                'is_active' => false,
                'revoked_at' => now(),
            ]) > 0;
    }

    /**
     * Revoke all sessions except current
     */
    public function revokeOtherSessions(User $user, ?string $currentSessionId): int
    {
        $query = DeviceSession::where('user_id', $user->id)
            ->where('is_active', true);

        if ($currentSessionId) {
            $query->where('id', '!=', $currentSessionId);
        }

        return $query->update([
            'is_active' => false,
            'revoked_at' => now(),
        ]);
    }

    /**
     * Revoke ALL sessions for a user (full logout)
     */
    public function revokeAllSessions(User $user): int
    {
        return DeviceSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'revoked_at' => now(),
            ]);
    }

    /**
     * Update last active timestamp
     */
    public function touchSession(string $sessionId): void
    {
        DeviceSession::where('id', $sessionId)
            ->update(['last_active_at' => now()]);
    }

    /**
     * Get push tokens for a user (for notifications)
     */
    public function getPushTokens(User $user): array
    {
        return DeviceSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNotNull('push_token')
            ->pluck('push_token')
            ->toArray();
    }

    /**
     * Detect device type from User-Agent
     */
    protected function detectDeviceType(Request $request): string
    {
        $ua = strtolower($request->userAgent() ?? '');

        if (str_contains($ua, 'iphone') || str_contains($ua, 'ipad')) {
            return 'ios';
        }

        if (str_contains($ua, 'android')) {
            return 'android';
        }

        return 'web';
    }

    /**
     * Detect device name from User-Agent
     */
    protected function detectDeviceName(Request $request): string
    {
        $ua = $request->userAgent() ?? '';

        // Extract device model from UA
        if (preg_match('/\(([^)]+)\)/', $ua, $matches)) {
            $device = explode(';', $matches[1]);
            return trim($device[0] ?? 'Unknown Device');
        }

        return 'Unknown Device';
    }

    /**
     * Detect OS version from User-Agent
     */
    protected function detectOsVersion(Request $request): ?string
    {
        $ua = $request->userAgent() ?? '';

        // iOS version
        if (preg_match('/OS (\d+[_\.]\d+)/', $ua, $matches)) {
            return str_replace('_', '.', $matches[1]);
        }

        // Android version
        if (preg_match('/Android (\d+\.?\d*)/', $ua, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Check for suspicious activity
     */
    public function detectAbuse(User $user): array
    {
        $warnings = [];

        // Check for too many active sessions
        $activeSessions = DeviceSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->count();

        if ($activeSessions > 10) {
            $warnings[] = 'too_many_sessions';
        }

        // Check for sessions from too many IPs in short time
        $recentIps = DeviceSession::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDay())
            ->distinct('ip_address')
            ->count('ip_address');

        if ($recentIps > 5) {
            $warnings[] = 'many_ips_recently';
        }

        return $warnings;
    }
}
