<?php

namespace App\Actions\Auth;

use App\Models\OtpRequest;
use App\Models\User;
use App\Models\Family;
use App\Models\FamilyUser;
use App\Models\UserStats;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class VerifyOtpAction
{
    /**
     * Verify OTP and create/update user
     * 
     * @param string $phone
     * @param string $code
     * @param string|null $name
     * @param string|null $referralCode
     * @return array{success: bool, message: string, user?: User, is_new_user?: bool}
     */
    public function execute(string $phone, string $code, ?string $name = null, ?string $referralCode = null): array
    {
        $phone = $this->normalizePhone($phone);

        // Find valid OTP request
        $otpRequest = OtpRequest::where('phone', $phone)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$otpRequest) {
            return [
                'success' => false,
                'message' => 'No valid code found. Please request a new one.',
            ];
        }

        // Check max attempts
        if ($otpRequest->hasMaxAttempts()) {
            return [
                'success' => false,
                'message' => 'Too many attempts. Please request a new code.',
            ];
        }

        // Verify code
        if (!Hash::check($code, $otpRequest->code_hash)) {
            $otpRequest->incrementAttempts();

            return [
                'success' => false,
                'message' => 'Invalid code. Please try again.',
                'attempts_remaining' => 5 - $otpRequest->attempts,
            ];
        }

        // Mark OTP as verified
        $otpRequest->markAsVerified();

        // Create or get user
        return DB::transaction(function () use ($phone, $name, $referralCode) {
            $existingUser = User::where('phone', $phone)->first();
            $isNewUser = !$existingUser;

            if ($isNewUser) {
                $user = User::create([
                    'phone' => $phone,
                    'name' => $name ?? 'User',
                    'is_verified' => true,
                ]);

                // Create default family
                $family = Family::create([
                    'name' => ($name ?? 'User') . "'s Family",
                    'type' => 'family',
                ]);

                // Add user as owner
                FamilyUser::create([
                    'family_id' => $family->id,
                    'user_id' => $user->id,
                    'role' => 'owner',
                    'status' => 'active',
                    'joined_at' => now(),
                ]);

                // Create user stats
                UserStats::create([
                    'user_id' => $user->id,
                    'xp' => 0,
                    'level' => 1,
                    'streak_days' => 0,
                ]);

                // Handle referral if present
                if ($referralCode) {
                    $this->processReferral($user, $referralCode);
                }
            } else {
                $user = $existingUser;
                $user->update(['is_verified' => true]);
            }

            return [
                'success' => true,
                'message' => $isNewUser ? 'Account created successfully.' : 'Welcome back!',
                'user' => $user,
                'is_new_user' => $isNewUser,
            ];
        });
    }

    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) === 11 && !str_starts_with($phone, '55')) {
            $phone = '55' . $phone;
        }

        return '+' . $phone;
    }

    protected function processReferral(User $user, string $referralCode): void
    {
        // TODO: Implement referral processing
        // Find referrer by share_link code, create pending referral
    }
}
