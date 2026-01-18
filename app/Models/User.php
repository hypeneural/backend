<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'phone',
        'name',
        'avatar',
        'email',
        'is_verified',
        'onboarding_completed',
        'primary_city_id',
        'last_lat',
        'last_lng',
        'last_location_at',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'onboarding_completed' => 'boolean',
            'last_lat' => 'decimal:8',
            'last_lng' => 'decimal:8',
            'last_location_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }

    // JWT Methods
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    // Relationships
    public function families()
    {
        return $this->belongsToMany(Family::class, 'family_users')
            ->withPivot('role', 'status', 'permissions', 'nickname', 'joined_at')
            ->wherePivot('status', 'active');
    }

    public function familyUsers()
    {
        return $this->hasMany(FamilyUser::class);
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function memories()
    {
        return $this->hasMany(Memory::class);
    }

    public function plans()
    {
        return $this->hasMany(Plan::class);
    }

    public function stats()
    {
        return $this->hasOne(UserStats::class);
    }

    public function badges()
    {
        return $this->hasMany(UserBadge::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function notificationSettings()
    {
        return $this->hasOne(NotificationSetting::class);
    }

    public function shareLinks()
    {
        return $this->hasMany(ShareLink::class, 'created_by');
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class, 'referrer_user_id');
    }

    // Helper Methods
    public function getPrimaryFamily()
    {
        return $this->families()->first();
    }
}
