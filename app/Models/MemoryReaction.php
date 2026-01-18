<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemoryReaction extends Model
{
    protected $table = 'memory_reactions';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'memory_id',
        'user_id',
        'emoji',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function memory()
    {
        return $this->belongsTo(Memory::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
