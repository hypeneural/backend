<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserStats;
use Illuminate\Support\Facades\Cache;

/**
 * Gamification Service
 * 
 * Handles XP, levels, streaks, and badges.
 */
class GamificationService
{
    /**
     * XP rewards for actions
     */
    public const XP_REWARDS = [
        'complete_onboarding' => 100,
        'save_experience' => 5,
        'create_review' => 25,
        'create_memory' => 10,
        'complete_plan' => 50,
        'referral_qualified' => 100,
        'streak_daily' => 5, // Multiplied by streak days
    ];

    /**
     * Daily limits for actions
     */
    public const DAILY_LIMITS = [
        'save_experience' => 20,
        'create_review' => 5,
        'create_memory' => 10,
    ];

    /**
     * Level thresholds
     */
    public static function getLevelThreshold(int $level): int
    {
        if ($level <= 1)
            return 0;
        if ($level == 2)
            return 100;
        if ($level == 3)
            return 300;
        if ($level == 4)
            return 600;
        if ($level == 5)
            return 1000;

        // After level 5: +500 per level
        return 1000 + (($level - 5) * 500);
    }

    /**
     * Calculate level from XP
     */
    public static function calculateLevel(int $xp): int
    {
        $level = 1;
        while (self::getLevelThreshold($level + 1) <= $xp) {
            $level++;
            if ($level >= 100)
                break; // Max level cap
        }
        return $level;
    }

    /**
     * Award XP to user for an action
     */
    public function awardXp(User $user, string $action, array $metadata = []): array
    {
        if (!isset(self::XP_REWARDS[$action])) {
            return ['success' => false, 'message' => 'Unknown action'];
        }

        // Check daily limit
        if (isset(self::DAILY_LIMITS[$action])) {
            $dailyCount = $this->getDailyActionCount($user->id, $action);
            if ($dailyCount >= self::DAILY_LIMITS[$action]) {
                return ['success' => false, 'message' => 'Daily limit reached'];
            }
            $this->incrementDailyActionCount($user->id, $action);
        }

        $xpAmount = self::XP_REWARDS[$action];

        // Streak bonus for streak_daily
        if ($action === 'streak_daily' && isset($metadata['streak_days'])) {
            $xpAmount = $xpAmount * $metadata['streak_days'];
        }

        // Get or create stats
        $stats = UserStats::firstOrCreate(
            ['user_id' => $user->id],
            ['xp' => 0, 'level' => 1, 'streak_days' => 0, 'longest_streak' => 0]
        );

        $oldLevel = $stats->level;
        $stats->xp += $xpAmount;
        $stats->level = self::calculateLevel($stats->xp);
        $stats->save();

        $leveledUp = $stats->level > $oldLevel;

        return [
            'success' => true,
            'xp_awarded' => $xpAmount,
            'total_xp' => $stats->xp,
            'level' => $stats->level,
            'leveled_up' => $leveledUp,
        ];
    }

    /**
     * Update user streak
     */
    public function updateStreak(User $user): array
    {
        $stats = UserStats::firstOrCreate(
            ['user_id' => $user->id],
            ['xp' => 0, 'level' => 1, 'streak_days' => 0, 'longest_streak' => 0]
        );

        $lastActivityKey = "user:last_activity:{$user->id}";
        $lastActivity = Cache::get($lastActivityKey);

        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();

        if ($lastActivity) {
            $lastDate = \Carbon\Carbon::parse($lastActivity)->startOfDay();

            if ($lastDate->equalTo($today)) {
                // Already active today
                return ['streak_days' => $stats->streak_days];
            } elseif ($lastDate->equalTo($yesterday)) {
                // Continue streak
                $stats->streak_days++;
            } else {
                // Streak broken
                $stats->streak_days = 1;
            }
        } else {
            // First activity
            $stats->streak_days = 1;
        }

        // Update longest streak
        if ($stats->streak_days > $stats->longest_streak) {
            $stats->longest_streak = $stats->streak_days;
        }

        $stats->save();
        Cache::put($lastActivityKey, now()->toISOString(), now()->addDays(2));

        // Award streak XP
        $this->awardXp($user, 'streak_daily', ['streak_days' => $stats->streak_days]);

        return [
            'streak_days' => $stats->streak_days,
            'longest_streak' => $stats->longest_streak,
        ];
    }

    /**
     * Get daily action count from Redis
     */
    protected function getDailyActionCount(string $userId, string $action): int
    {
        $key = "user:{$userId}:daily:{$action}:" . now()->format('Y-m-d');
        return (int) Cache::get($key, 0);
    }

    /**
     * Increment daily action count
     */
    protected function incrementDailyActionCount(string $userId, string $action): void
    {
        $key = "user:{$userId}:daily:{$action}:" . now()->format('Y-m-d');
        $count = Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->endOfDay());
    }

    /**
     * Calculate level progress (0.0 - 1.0)
     */
    public static function calculateProgress(int $xp, int $level): float
    {
        $currentThreshold = self::getLevelThreshold($level);
        $nextThreshold = self::getLevelThreshold($level + 1);

        if ($nextThreshold <= $currentThreshold)
            return 0;

        $progress = ($xp - $currentThreshold) / ($nextThreshold - $currentThreshold);
        return round(min(1, max(0, $progress)), 2);
    }
}
