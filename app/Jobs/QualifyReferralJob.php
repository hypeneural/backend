<?php

namespace App\Jobs;

use App\Models\Referral;
use App\Models\User;
use App\Models\UserStats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class QualifyReferralJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        protected string $referredUserId,
        protected string $actionType // 'plan_created' or 'favorite_added'
    ) {
    }

    /**
     * Qualify a referral after user takes a qualifying action
     * Actions: create first plan OR save first experience
     */
    public function handle(): void
    {
        $referral = Referral::where('referred_user_id', $this->referredUserId)
            ->where('status', 'pending')
            ->first();

        if (!$referral) {
            return;
        }

        // Fraud detection: check for suspicious patterns
        if ($this->isSuspicious($referral)) {
            $referral->markAsFraud();
            return;
        }

        // Qualify the referral
        $referral->qualify();

        // Award XP to referrer
        $referrerStats = UserStats::firstOrCreate(
            ['user_id' => $referral->referrer_user_id],
            ['xp' => 0, 'level' => 1, 'streak_days' => 0]
        );

        $referrerStats->addXp(100); // 100 XP for successful referral
        $referrerStats->increment('total_referrals');

        // Award XP to referred user
        $referredStats = UserStats::where('user_id', $this->referredUserId)->first();
        if ($referredStats) {
            $referredStats->addXp(50); // 50 XP for joining via referral
        }
    }

    protected function isSuspicious(Referral $referral): bool
    {
        // Check 1: Same IP hash
        if ($referral->ip_hash) {
            $sameIpCount = Referral::where('referrer_user_id', $referral->referrer_user_id)
                ->where('ip_hash', $referral->ip_hash)
                ->count();

            if ($sameIpCount > 3) {
                return true;
            }
        }

        // Check 2: Same device fingerprint
        if ($referral->device_fingerprint) {
            $sameDeviceCount = Referral::where('referrer_user_id', $referral->referrer_user_id)
                ->where('device_fingerprint', $referral->device_fingerprint)
                ->count();

            if ($sameDeviceCount > 2) {
                return true;
            }
        }

        // Check 3: Too many referrals in short time
        $recentReferrals = Referral::where('referrer_user_id', $referral->referrer_user_id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentReferrals > 5) {
            return true;
        }

        return false;
    }
}
