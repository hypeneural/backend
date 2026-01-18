<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'family_id',
        'experience_id',
        'list_id',
        'scope',
        'saved_at',
    ];

    protected function casts(): array
    {
        return [
            'saved_at' => 'datetime',
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

    public function experience()
    {
        return $this->belongsTo(Experience::class);
    }

    public function list()
    {
        return $this->belongsTo(FavoriteList::class, 'list_id');
    }
}
