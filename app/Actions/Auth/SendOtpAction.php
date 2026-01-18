<?php

namespace App\Actions\Auth;

use App\Models\OtpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class SendOtpAction
{
    /**
     * Send OTP to phone number with rate limiting
     * 
     * @param string $phone
     * @param string|null $ipAddress
     * @param string|null $deviceFingerprint
     * @return array{success: bool, message: string, expires_at?: Carbon}
     */
    public function execute(string $phone, ?string $ipAddress = null, ?string $deviceFingerprint = null): array
    {
        $phone = $this->normalizePhone($phone);
        $ipHash = $ipAddress ? hash('sha256', $ipAddress) : null;

        // Rate limiting: check if phone sent OTP in last minute
        if ($this->isRateLimited($phone, $ipHash)) {
            return [
                'success' => false,
                'message' => 'Please wait before requesting a new code.',
            ];
        }

        // Generate 6-digit OTP
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(5);

        // Invalidate previous OTPs for this phone
        OtpRequest::where('phone', $phone)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        // Create new OTP request
        $otpRequest = OtpRequest::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => $expiresAt,
            'attempts' => 0,
            'last_sent_at' => now(),
            'ip_hash' => $ipHash,
            'device_fingerprint' => $deviceFingerprint,
        ]);

        // Set rate limit in Redis
        $this->setRateLimit($phone, $ipHash);

        // TODO: Send SMS via provider (Twilio, etc.)
        // For now, log the code in development
        if (app()->environment('local', 'development')) {
            logger()->info("OTP for {$phone}: {$code}");
        }

        return [
            'success' => true,
            'message' => 'Code sent successfully.',
            'expires_at' => $expiresAt,
            // Only include code in development for testing
            'code' => app()->environment('local', 'development') ? $code : null,
        ];
    }

    protected function normalizePhone(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Ensure Brazilian format (+55)
        if (strlen($phone) === 11 && !str_starts_with($phone, '55')) {
            $phone = '55' . $phone;
        }

        return '+' . $phone;
    }

    protected function isRateLimited(string $phone, ?string $ipHash): bool
    {
        // Check phone rate limit (1 per minute)
        $phoneKey = "otp:ratelimit:phone:{$phone}";
        if (Redis::exists($phoneKey)) {
            return true;
        }

        // Check IP rate limit (5 per minute)
        if ($ipHash) {
            $ipKey = "otp:ratelimit:ip:{$ipHash}";
            $count = (int) Redis::get($ipKey);
            if ($count >= 5) {
                return true;
            }
        }

        return false;
    }

    protected function setRateLimit(string $phone, ?string $ipHash): void
    {
        $phoneKey = "otp:ratelimit:phone:{$phone}";
        Redis::setex($phoneKey, 60, '1');

        if ($ipHash) {
            $ipKey = "otp:ratelimit:ip:{$ipHash}";
            Redis::incr($ipKey);
            Redis::expire($ipKey, 60);
        }
    }
}
