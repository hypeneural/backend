<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FamilyInviteUse extends Model
{
    use HasUuids;

    protected $table = 'family_invite_uses';
    public $timestamps = false;

    protected $fillable = [
        'invite_id',
        'user_id',
        'used_at',
        'ip_hash',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    public function invite()
    {
        return $this->belongsTo(FamilyInvite::class, 'invite_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
