<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

/**
 * WeatherAPI.com Integration Service
 * 
 * Proxy service for WeatherAPI.com v1
 * - Hides API key from frontend
 * - Normalizes responses
 * - Caches results
 * 
 * @see https://www.weatherapi.com/docs/
 */
class WeatherApiService
{
    protected string $baseUrl = 'https://api.weatherapi.com/v1';
    protected ?string $apiKey;

    // Cache TTL in seconds
    protected array $cacheTtl = [
        'search' => 86400,    // 24 hours
        'current' => 300,     // 5 minutes
        'forecast' => 1800,   // 30 minutes
    ];

    public function __construct()
    {
        $this->apiKey = config('services.weatherapi.key');
    }

    /**
     * Search locations for autocomplete
     * 
     * @param string $query City name, postal code, or IP
     * @return array Normalized location results
     */
    public function search(string $query): array
    {
        if (strlen($query) < 2) {
            return ['locations' => []];
        }

        $cacheKey = "weather:search:" . md5($query);

        return Cache::remember($cacheKey, $this->cacheTtl['search'], function () use ($query) {
            $response = $this->makeRequest('/search.json', ['q' => $query]);

            return [
                'locations' => collect($response)->map(fn($loc) => [
                    'id' => $loc['id'],
                    'name' => $loc['name'],
                    'region' => $loc['region'],
                    'country' => $loc['country'],
                    'lat' => $loc['lat'],
                    'lon' => $loc['lon'],
                    'display_name' => "{$loc['name']}, {$loc['region']}, {$loc['country']}",
                ])->take(10)->values()->toArray(),
            ];
        });
    }

    /**
     * Get current weather
     * 
     * @param string $query City name, lat/lon, or location ID
     * @return array Normalized current weather
     */
    public function current(string $query): array
    {
        $cacheKey = "weather:current:" . md5($query);

        return Cache::remember($cacheKey, $this->cacheTtl['current'], function () use ($query) {
            $response = $this->makeRequest('/current.json', [
                'q' => $query,
                'lang' => 'pt',
                'aqi' => 'no',
            ]);

            return $this->normalizeCurrentResponse($response);
        });
    }

    /**
     * Get weather forecast
     * 
     * @param string $query City name, lat/lon, or location ID
     * @param int $days Number of forecast days (1-14)
     * @return array Normalized forecast
     */
    public function forecast(string $query, int $days = 3): array
    {
        $days = max(1, min(14, $days));
        $cacheKey = "weather:forecast:" . md5($query) . ":days:{$days}";

        return Cache::remember($cacheKey, $this->cacheTtl['forecast'], function () use ($query, $days) {
            $response = $this->makeRequest('/forecast.json', [
                'q' => $query,
                'days' => $days,
                'lang' => 'pt',
                'aqi' => 'no',
                'alerts' => 'no',
            ]);

            return $this->normalizeForecastResponse($response);
        });
    }

    /**
     * Make HTTP request to WeatherAPI
     */
    protected function makeRequest(string $endpoint, array $params = []): array
    {
        if (!$this->apiKey) {
            throw new \RuntimeException('WeatherAPI key not configured');
        }

        $params['key'] = $this->apiKey;

        try {
            $response = Http::timeout(10)
                ->retry(2, 500)
                ->get($this->baseUrl . $endpoint, $params);

            if (!$response->successful()) {
                $this->handleApiError($response->json());
            }

            return $response->json();
        } catch (RequestException $e) {
            throw new \RuntimeException('Weather service unavailable', 503, $e);
        }
    }

    /**
     * Handle WeatherAPI error codes
     */
    protected function handleApiError(array $response): void
    {
        $error = $response['error'] ?? [];
        $code = $error['code'] ?? 0;
        $message = $error['message'] ?? 'Unknown error';

        $errorMap = [
            1002 => ['status' => 401, 'message' => 'API key not provided'],
            2006 => ['status' => 401, 'message' => 'Invalid API key'],
            2007 => ['status' => 429, 'message' => 'API quota exceeded'],
            2008 => ['status' => 403, 'message' => 'API key disabled'],
            1003 => ['status' => 400, 'message' => 'Query parameter missing'],
            1005 => ['status' => 400, 'message' => 'Invalid request URL'],
            1006 => ['status' => 404, 'message' => 'Location not found'],
            9999 => ['status' => 500, 'message' => 'Internal error'],
        ];

        $error = $errorMap[$code] ?? ['status' => 500, 'message' => $message];

        throw new \RuntimeException($error['message'], $error['status']);
    }

    /**
     * Normalize current weather response
     */
    protected function normalizeCurrentResponse(array $response): array
    {
        $location = $response['location'];
        $current = $response['current'];

        return [
            'location' => [
                'name' => $location['name'],
                'region' => $location['region'],
                'country' => $location['country'],
                'lat' => $location['lat'],
                'lon' => $location['lon'],
                'tz_id' => $location['tz_id'],
                'localtime' => $location['localtime'],
            ],
            'current' => [
                'temp_c' => $current['temp_c'],
                'temp_f' => $current['temp_f'],
                'feelslike_c' => $current['feelslike_c'],
                'feelslike_f' => $current['feelslike_f'],
                'humidity' => $current['humidity'],
                'wind_kph' => $current['wind_kph'],
                'wind_dir' => $current['wind_dir'],
                'pressure_mb' => $current['pressure_mb'],
                'uv' => $current['uv'],
                'is_day' => (bool) $current['is_day'],
                'condition' => [
                    'text' => $current['condition']['text'],
                    'icon' => 'https:' . $current['condition']['icon'],
                    'code' => $current['condition']['code'],
                ],
                'last_updated' => $current['last_updated'],
            ],
        ];
    }

    /**
     * Normalize forecast response
     */
    protected function normalizeForecastResponse(array $response): array
    {
        $location = $response['location'];
        $current = $response['current'];
        $forecastDays = $response['forecast']['forecastday'];

        return [
            'location' => [
                'name' => $location['name'],
                'region' => $location['region'],
                'country' => $location['country'],
                'lat' => $location['lat'],
                'lon' => $location['lon'],
                'tz_id' => $location['tz_id'],
                'localtime' => $location['localtime'],
            ],
            'current' => [
                'temp_c' => $current['temp_c'],
                'feelslike_c' => $current['feelslike_c'],
                'humidity' => $current['humidity'],
                'wind_kph' => $current['wind_kph'],
                'is_day' => (bool) $current['is_day'],
                'condition' => [
                    'text' => $current['condition']['text'],
                    'icon' => 'https:' . $current['condition']['icon'],
                    'code' => $current['condition']['code'],
                ],
            ],
            'forecast' => collect($forecastDays)->map(fn($day) => [
                'date' => $day['date'],
                'date_epoch' => $day['date_epoch'],
                'min_c' => $day['day']['mintemp_c'],
                'max_c' => $day['day']['maxtemp_c'],
                'avg_temp_c' => $day['day']['avgtemp_c'],
                'max_wind_kph' => $day['day']['maxwind_kph'],
                'avg_humidity' => $day['day']['avghumidity'],
                'chance_of_rain' => $day['day']['daily_chance_of_rain'],
                'uv' => $day['day']['uv'],
                'condition' => [
                    'text' => $day['day']['condition']['text'],
                    'icon' => 'https:' . $day['day']['condition']['icon'],
                    'code' => $day['day']['condition']['code'],
                ],
                'sunrise' => $day['astro']['sunrise'],
                'sunset' => $day['astro']['sunset'],
                'hours' => collect($day['hour'] ?? [])->map(fn($h) => [
                    'time' => $h['time'],
                    'temp_c' => $h['temp_c'],
                    'condition' => [
                        'text' => $h['condition']['text'],
                        'icon' => 'https:' . $h['condition']['icon'],
                    ],
                    'chance_of_rain' => $h['chance_of_rain'],
                    'wind_kph' => $h['wind_kph'],
                ])->values()->toArray(),
            ])->values()->toArray(),
        ];
    }

    /**
     * Clear weather cache for a location
     */
    public function clearCache(string $query): void
    {
        $hash = md5($query);
        Cache::forget("weather:search:{$hash}");
        Cache::forget("weather:current:{$hash}");

        for ($days = 1; $days <= 14; $days++) {
            Cache::forget("weather:forecast:{$hash}:days:{$days}");
        }
    }
}
