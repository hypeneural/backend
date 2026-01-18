<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'experience_id',
        'user_id',
        'rating',
        'comment',
        'tags',
        'visibility',
        'visited_at',
        'helpful_count',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'tags' => 'array',
            'visited_at' => 'date',
            'helpful_count' => 'integer',
        ];
    }

    public function experience()
    {
        return $this->belongsTo(Experience::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function photos()
    {
        return $this->hasMany(ReviewPhoto::class);
    }

    public function helpfulVotes()
    {
        return $this->hasMany(ReviewHelpful::class);
    }

    public function comments()
    {
        return $this->hasMany(ReviewComment::class);
    }

    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }
}
