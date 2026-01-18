<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OtpRequest extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'phone',
        'code_hash',
        'expires_at',
        'attempts',
        'last_sent_at',
        'ip_hash',
        'device_fingerprint',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'verified_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function hasMaxAttempts(): bool
    {
        return $this->attempts >= 5;
    }

    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    public function markAsVerified(): void
    {
        $this->update(['verified_at' => now()]);
    }
}
