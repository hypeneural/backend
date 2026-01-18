<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CityTrending extends Model
{
    protected $table = 'city_trending';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'city_id',
        'experience_id',
        'position',
        'score',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'score' => 'float',
            'calculated_at' => 'datetime',
        ];
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function experience()
    {
        return $this->belongsTo(Experience::class);
    }
}
