<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'state',
        'country',
        'lat',
        'lng',
        'timezone',
        'population',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:8',
            'lng' => 'decimal:8',
            'population' => 'integer',
        ];
    }

    public function places()
    {
        return $this->hasMany(Place::class);
    }

    public function experiences()
    {
        return $this->hasMany(Experience::class);
    }

    public function trending()
    {
        return $this->hasMany(CityTrending::class);
    }
}
