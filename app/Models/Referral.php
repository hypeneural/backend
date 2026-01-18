<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'referrer_user_id',
        'referred_user_id',
        'share_link_id',
        'status',
        'qualified_at',
        'rewarded_at',
        'ip_hash',
        'device_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'qualified_at' => 'datetime',
            'rewarded_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referred()
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function shareLink()
    {
        return $this->belongsTo(ShareLink::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function qualify(): void
    {
        $this->update([
            'status' => 'qualified',
            'qualified_at' => now(),
        ]);
    }

    public function markAsRewarded(): void
    {
        $this->update([
            'status' => 'rewarded',
            'rewarded_at' => now(),
        ]);
    }

    public function markAsFraud(): void
    {
        $this->update(['status' => 'fraud']);
    }
}
