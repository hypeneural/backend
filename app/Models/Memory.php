<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Memory extends Model
{
    use HasUuids, SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'family_id',
        'plan_id',
        'experience_id',
        'image_url',
        'thumbnail_url',
        'caption',
        'visibility',
        'taken_at',
    ];

    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'created_at' => 'datetime',
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

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function experience()
    {
        return $this->belongsTo(Experience::class);
    }

    public function reactions()
    {
        return $this->hasMany(MemoryReaction::class);
    }

    public function comments()
    {
        return $this->hasMany(MemoryComment::class);
    }
}
