<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Experience extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'title',
        'mission_title',
        'summary',
        'category_id',
        'place_id',
        'city_id',
        'lat',
        'lng',
        'badges',
        'age_tags',
        'vibe',
        'duration_min',
        'duration_max',
        'price_level',
        'price_label',
        'weather',
        'practical',
        'tips',
        'cover_image',
        'gallery',
        'saves_count',
        'reviews_count',
        'average_rating',
        'trending_score',
        'status',
        'source',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:8',
            'lng' => 'decimal:8',
            'badges' => 'array',
            'age_tags' => 'array',
            'vibe' => 'array',
            'duration_min' => 'integer',
            'duration_max' => 'integer',
            'weather' => 'array',
            'practical' => 'array',
            'tips' => 'array',
            'gallery' => 'array',
            'saves_count' => 'integer',
            'reviews_count' => 'integer',
            'average_rating' => 'decimal:1',
            'trending_score' => 'float',
            'published_at' => 'datetime',
        ];
    }

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function searchRecord()
    {
        return $this->hasOne(ExperienceSearch::class, 'experience_id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function memories()
    {
        return $this->hasMany(Memory::class);
    }

    public function metricsDaily()
    {
        return $this->hasMany(ExperienceMetricsDaily::class);
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeInCity($query, $cityId)
    {
        return $query->where('city_id', $cityId);
    }

    // Helpers
    public function getResolvedLat(): float
    {
        return $this->lat ?? $this->place->lat;
    }

    public function getResolvedLng(): float
    {
        return $this->lng ?? $this->place->lng;
    }

    public function getDurationBucket(): string
    {
        $avg = ($this->duration_min + $this->duration_max) / 2;

        if ($avg < 60) {
            return 'quick';
        } elseif ($avg <= 180) {
            return 'half';
        }

        return 'full';
    }

    public function getAgeTagsMask(): int
    {
        $mask = 0;
        $map = ['baby' => 1, 'toddler' => 2, 'kid' => 4, 'teen' => 8, 'all' => 16];

        foreach ($this->age_tags ?? [] as $tag) {
            $mask |= $map[$tag] ?? 0;
        }

        return $mask;
    }

    public function getWeatherMask(): int
    {
        $mask = 0;
        $map = ['sun' => 1, 'rain' => 2, 'any' => 4];

        foreach ($this->weather ?? [] as $w) {
            $mask |= $map[$w] ?? 0;
        }

        return $mask;
    }

    public function getPracticalMask(): int
    {
        $mask = 0;
        $map = [
            'parking' => 1,
            'bathroom' => 2,
            'food' => 4,
            'stroller' => 8,
            'accessibility' => 16,
            'changing_table' => 32,
        ];

        foreach ($this->practical ?? [] as $key => $value) {
            if ($value && isset($map[$key])) {
                $mask |= $map[$key];
            }
        }

        return $mask;
    }
}
