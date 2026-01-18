<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Family extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
        'avatar',
        'type',
        'vibe_preset',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'family_users')
            ->withPivot('role', 'status', 'permissions', 'nickname', 'joined_at')
            ->wherePivot('status', 'active');
    }

    public function familyUsers()
    {
        return $this->hasMany(FamilyUser::class);
    }

    public function dependents()
    {
        return $this->hasMany(Dependent::class);
    }

    public function preferences()
    {
        return $this->hasOne(FamilyPreference::class);
    }

    public function preferenceCategories()
    {
        return $this->hasMany(FamilyPreferenceCategory::class);
    }

    public function invites()
    {
        return $this->hasMany(FamilyInvite::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function memories()
    {
        return $this->hasMany(Memory::class);
    }

    public function plans()
    {
        return $this->hasMany(Plan::class);
    }

    public function getOwner()
    {
        return $this->users()->wherePivot('role', 'owner')->first();
    }

    public function hasUser(User $user): bool
    {
        return $this->users()->where('users.id', $user->id)->exists();
    }
}
