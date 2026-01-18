<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ShareLink extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'type',
        'target_id',
        'code',
        'created_by',
        'expires_at',
        'clicks_count',
        'utm_source',
        'utm_campaign',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'clicks_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class);
    }

    public static function generateCode(): string
    {
        return strtoupper(Str::random(8));
    }

    public function incrementClicks(): void
    {
        $this->increment('clicks_count');
    }
}
