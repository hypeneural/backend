<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FamilyPreference extends Model
{
    use HasUuids;

    protected $primaryKey = 'family_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'family_id',
        'max_distance_km',
        'default_price',
        'avoid',
    ];

    protected function casts(): array
    {
        return [
            'max_distance_km' => 'integer',
            'avoid' => 'array',
            'updated_at' => 'datetime',
        ];
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }
}
