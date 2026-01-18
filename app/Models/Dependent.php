<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Dependent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'family_id',
        'name',
        'avatar',
        'birth_date',
        'age_group',
        'restrictions',
        'interests',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'restrictions' => 'array',
            'interests' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
