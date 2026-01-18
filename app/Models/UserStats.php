<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserStats extends Model
{
    protected $table = 'user_stats';
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'xp',
        'level',
        'streak_days',
        'last_action_at',
        'total_saves',
        'total_reviews',
        'total_plans',
        'total_referrals',
    ];

    protected function casts(): array
    {
        return [
            'xp' => 'integer',
            'level' => 'integer',
            'streak_days' => 'integer',
            'last_action_at' => 'datetime',
            'total_saves' => 'integer',
            'total_reviews' => 'integer',
            'total_plans' => 'integer',
            'total_referrals' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function addXp(int $amount): void
    {
        $this->increment('xp', $amount);
        $this->checkLevelUp();
    }

    protected function checkLevelUp(): void
    {
        $xpPerLevel = 1000;
        $newLevel = (int) floor($this->xp / $xpPerLevel) + 1;

        if ($newLevel > $this->level) {
            $this->update(['level' => $newLevel]);
        }
    }

    public function updateStreak(): void
    {
        $today = now()->startOfDay();
        $lastAction = $this->last_action_at?->startOfDay();

        if ($lastAction === null || $lastAction->diffInDays($today) > 1) {
            $this->update([
                'streak_days' => 1,
                'last_action_at' => now(),
            ]);
        } elseif ($lastAction->diffInDays($today) === 1) {
            $this->update([
                'streak_days' => $this->streak_days + 1,
                'last_action_at' => now(),
            ]);
        } else {
            $this->update(['last_action_at' => now()]);
        }
    }
}
