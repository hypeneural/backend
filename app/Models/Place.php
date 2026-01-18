<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Place extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'address',
        'city_id',
        'neighborhood',
        'lat',
        'lng',
        'google_place_id',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:8',
            'lng' => 'decimal:8',
            'created_at' => 'datetime',
        ];
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function experiences()
    {
        return $this->hasMany(Experience::class);
    }
}
