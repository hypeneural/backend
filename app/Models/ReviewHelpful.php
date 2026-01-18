<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewHelpful extends Model
{
    protected $table = 'review_helpful';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'review_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
