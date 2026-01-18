<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FamilyInvite extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'family_id',
        'plan_id',
        'type',
        'code',
        'token_hash',
        'max_uses',
        'uses_count',
        'expires_at',
        'created_by',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function uses()
    {
        return $this->hasMany(FamilyInviteUse::class, 'invite_id');
    }

    public function isValid(): bool
    {
        return !$this->isExpired() &&
            !$this->isRevoked() &&
            !$this->hasReachedMaxUses();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function hasReachedMaxUses(): bool
    {
        return $this->uses_count >= $this->max_uses;
    }

    public static function generateCode(): string
    {
        return 'BORA-' . strtoupper(Str::random(6));
    }
}
