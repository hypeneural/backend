<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExperienceSearch extends Model
{
    protected $table = 'experience_search';
    protected $primaryKey = 'experience_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'experience_id',
        'title',
        'mission_title',
        'cover_image',
        'category_id',
        'city_id',
        'lat',
        'lng',
        'price_level',
        'duration_bucket',
        'age_tags_mask',
        'weather_mask',
        'practical_mask',
        'saves_count',
        'reviews_count',
        'average_rating',
        'trending_score',
        'search_text',
        'status',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:8',
            'lng' => 'decimal:8',
            'age_tags_mask' => 'integer',
            'weather_mask' => 'integer',
            'practical_mask' => 'integer',
            'saves_count' => 'integer',
            'reviews_count' => 'integer',
            'average_rating' => 'decimal:1',
            'trending_score' => 'float',
            'updated_at' => 'datetime',
        ];
    }

    public function experience()
    {
        return $this->belongsTo(Experience::class, 'experience_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
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

    public function scopeInBbox($query, float $west, float $south, float $east, float $north)
    {
        return $query->whereBetween('lat', [$south, $north])
            ->whereBetween('lng', [$west, $east]);
    }

    public function scopeWithAgeTag($query, int $mask)
    {
        return $query->whereRaw('(age_tags_mask & ?) > 0', [$mask]);
    }

    public function scopeWithWeather($query, int $mask)
    {
        return $query->whereRaw('(weather_mask & ?) > 0', [$mask]);
    }

    public function scopeWithPractical($query, int $mask)
    {
        return $query->whereRaw('(practical_mask & ?) = ?', [$mask, $mask]);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->whereRaw('MATCH(search_text) AGAINST(? IN NATURAL LANGUAGE MODE)', [$term]);
    }
}
