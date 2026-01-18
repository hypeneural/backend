<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Weather Service
 * 
 * Manages weather cache for cities to enable weather-based filtering.
 * Can be extended to integrate with real weather APIs (OpenWeather, etc).
 */
class WeatherService
{
    /**
     * Get current weather for a city
     */
    public function getCurrentWeather(string $cityId): ?array
    {
        $cacheKey = "weather:city:{$cityId}:current";

        return Cache::remember($cacheKey, 1800, function () use ($cityId) {
            $weather = DB::table('city_weather_cache')
                ->where('city_id', $cityId)
                ->where('date', Carbon::today()->toDateString())
                ->first();

            if (!$weather) {
                return null;
            }

            return [
                'condition' => $weather->condition,
                'temp_min' => $weather->temp_min,
                'temp_max' => $weather->temp_max,
                'humidity' => $weather->humidity,
                'rain_probability' => $weather->rain_probability,
                'is_rainy' => in_array($weather->condition, ['rain', 'storm']),
                'fetched_at' => $weather->fetched_at,
            ];
        });
    }

    /**
     * Check if it's currently raining in a city
     */
    public function isRaining(string $cityId): bool
    {
        $weather = $this->getCurrentWeather($cityId);
        return $weather['is_rainy'] ?? false;
    }

    /**
     * Get weather condition string for filtering
     */
    public function getCondition(string $cityId): string
    {
        $weather = $this->getCurrentWeather($cityId);

        if (!$weather) {
            return 'sun'; // Default to sun if no data
        }

        return match ($weather['condition']) {
            'rain', 'storm' => 'rain',
            'cloud' => 'any',
            default => 'sun',
        };
    }

    /**
     * Update weather cache for a city
     * Can be called by a scheduled job that fetches from weather API
     */
    public function updateWeatherCache(
        string $cityId,
        string $condition,
        ?float $tempMin = null,
        ?float $tempMax = null,
        ?int $humidity = null,
        int $rainProbability = 0,
        ?array $hourly = null
    ): void {
        DB::table('city_weather_cache')->updateOrInsert(
            [
                'city_id' => $cityId,
                'date' => Carbon::today()->toDateString(),
            ],
            [
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'condition' => $condition,
                'temp_min' => $tempMin,
                'temp_max' => $tempMax,
                'humidity' => $humidity,
                'rain_probability' => $rainProbability,
                'hourly' => $hourly ? json_encode($hourly) : null,
                'fetched_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Clear cache
        Cache::forget("weather:city:{$cityId}:current");
    }

    /**
     * Seed weather data for testing
     */
    public function seedTestData(string $cityId, string $condition = 'sun'): void
    {
        $this->updateWeatherCache(
            cityId: $cityId,
            condition: $condition,
            tempMin: 18.0,
            tempMax: 28.0,
            humidity: 65,
            rainProbability: $condition === 'rain' ? 80 : 10
        );
    }
}
