<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'family_id',
        'title',
        'date',
        'status',
        'visibility',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function collaborators()
    {
        return $this->hasMany(PlanCollaborator::class);
    }

    public function experiences()
    {
        return $this->belongsToMany(Experience::class, 'plan_experiences')
            ->withPivot('order', 'time_slot', 'notes')
            ->orderByPivot('order');
    }

    public function memories()
    {
        return $this->hasMany(Memory::class);
    }

    public function isOwner(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function hasCollaborator(User $user): bool
    {
        return $this->collaborators()->where('user_id', $user->id)->exists();
    }
}
