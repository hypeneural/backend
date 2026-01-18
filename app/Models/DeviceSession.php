<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceSession extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'device_type',
        'os_version',
        'app_version',
        'push_token',
        'ip_address',
        'user_agent',
        'last_active_at',
        'is_active',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_active_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for active sessions only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if session is active
     */
    public function isActive(): bool
    {
        return $this->is_active && is_null($this->revoked_at);
    }

    /**
     * Check if session has push token
     */
    public function hasPushToken(): bool
    {
        return !empty($this->push_token);
    }

    /**
     * Get display name for session
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->device_name) {
            return $this->device_name;
        }

        return match ($this->device_type) {
            'ios' => 'iPhone/iPad',
            'android' => 'Android',
            default => 'Navegador Web',
        };
    }
}
