<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlanCollaborator extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'plan_id',
        'user_id',
        'role',
        'invited_at',
        'accepted_at',
        'invited_by',
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function canEdit(): bool
    {
        return in_array($this->role, ['owner', 'editor']);
    }
}
