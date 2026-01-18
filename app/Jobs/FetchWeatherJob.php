<?php

namespace App\Jobs;

use App\Services\WeatherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Fetch Weather Job
 * 
 * Fetches weather data from external API and updates city_weather_cache.
 * Should run every 3-6 hours.
 * 
 * Supports: OpenWeatherMap (free tier: 1000 calls/day)
 */
class FetchWeatherJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(WeatherService $weatherService): void
    {
        // Get all active cities
        $cities = DB::table('cities')
            ->select(['id', 'name', 'state', 'lat', 'lng'])
            ->get();

        $apiKey = config('services.openweather.api_key');

        if (!$apiKey) {
            // No API key - use fallback test data
            $this->seedTestData($weatherService, $cities);
            return;
        }

        foreach ($cities as $city) {
            try {
                $this->fetchAndCacheWeather($city, $apiKey, $weatherService);
            } catch (\Exception $e) {
                // Log error and continue with next city
                report($e);
            }

            // Rate limiting - 60 calls/minute on free tier
            usleep(100000); // 100ms delay
        }

        info('FetchWeatherJob completed for ' . $cities->count() . ' cities');
    }

    /**
     * Fetch weather from OpenWeatherMap and cache it
     */
    protected function fetchAndCacheWeather($city, string $apiKey, WeatherService $weatherService): void
    {
        $response = Http::get('https://api.openweathermap.org/data/2.5/weather', [
            'lat' => $city->lat,
            'lon' => $city->lng,
            'appid' => $apiKey,
            'units' => 'metric',
            'lang' => 'pt_br',
        ]);

        if (!$response->successful()) {
            throw new \Exception("OpenWeather API error for {$city->name}");
        }

        $data = $response->json();

        // Map OpenWeather conditions to our simplified conditions
        $condition = $this->mapCondition($data['weather'][0]['main'] ?? 'Clear');

        $weatherService->updateWeatherCache(
            cityId: $city->id,
            condition: $condition,
            tempMin: $data['main']['temp_min'] ?? null,
            tempMax: $data['main']['temp_max'] ?? null,
            humidity: $data['main']['humidity'] ?? null,
            rainProbability: $this->calculateRainProbability($data)
        );
    }

    /**
     * Map OpenWeather condition to our simplified conditions
     */
    protected function mapCondition(string $owCondition): string
    {
        return match (strtolower($owCondition)) {
            'rain', 'drizzle', 'shower rain' => 'rain',
            'thunderstorm' => 'storm',
            'snow' => 'snow',
            'clouds', 'mist', 'fog', 'haze' => 'cloud',
            default => 'sun',
        };
    }

    /**
     * Calculate rain probability from OpenWeather data
     */
    protected function calculateRainProbability(array $data): int
    {
        // Check if it's currently raining
        if (isset($data['rain'])) {
            return 80;
        }

        // Check clouds percentage as proxy
        $clouds = $data['clouds']['all'] ?? 0;

        if ($clouds > 80)
            return 60;
        if ($clouds > 50)
            return 30;

        return 10;
    }

    /**
     * Seed test data when no API key is configured
     */
    protected function seedTestData(WeatherService $weatherService, $cities): void
    {
        // Randomly assign weather for testing
        $conditions = ['sun', 'sun', 'sun', 'cloud', 'rain'];

        foreach ($cities as $city) {
            $condition = $conditions[array_rand($conditions)];
            $weatherService->seedTestData($city->id, $condition);
        }

        info('FetchWeatherJob: Seeded test weather data (no API key configured)');
    }
}
