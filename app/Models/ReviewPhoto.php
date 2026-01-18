<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ReviewPhoto extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'review_id',
        'url',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    public function review()
    {
        return $this->belongsTo(Review::class);
    }
}
