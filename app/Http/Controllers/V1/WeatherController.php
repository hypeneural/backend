<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Services\WeatherApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group 19. Clima
 *
 * Endpoints para busca de clima e previsão do tempo.
 * Usa WeatherAPI.com como backend.
 * 
 * Todos os endpoints são públicos e cacheados.
 */
class WeatherController extends Controller
{
    public function __construct(
        protected WeatherApiService $weatherService
    ) {
    }

    /**
     * Buscar Localizações (Autocomplete)
     *
     * Busca cidades/locais para autocomplete.
     * Use debounce de 300-500ms no frontend.
     *
     * @unauthenticated
     *
     * @queryParam q string required Texto de busca (mínimo 2 caracteres). Example: São Paulo
     *
     * @response 200 scenario="Locais encontrados" {
     *   "data": {
     *     "locations": [
     *       {
     *         "id": 287907,
     *         "name": "Sao Paulo",
     *         "region": "Sao Paulo",
     *         "country": "Brazil",
     *         "lat": -23.53,
     *         "lon": -46.62,
     *         "display_name": "Sao Paulo, Sao Paulo, Brazil"
     *       }
     *     ]
     *   },
     *   "meta": {"success": true, "cached": true}
     * }
     *
     * @response 400 scenario="Query muito curta" {
     *   "data": null,
     *   "meta": {"success": false},
     *   "errors": [{"code": "INVALID_QUERY", "message": "Query deve ter pelo menos 2 caracteres"}]
     * }
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        try {
            $result = $this->weatherService->search($request->q);

            return response()->json([
                'data' => $result,
                'meta' => ['success' => true],
                'errors' => null,
            ])->header('Cache-Control', 'public, max-age=3600');

        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Clima Atual
     *
     * Retorna o clima atual para uma localização.
     * Aceita nome da cidade ou coordenadas (lat,lon).
     *
     * @unauthenticated
     *
     * @queryParam q string required Cidade ou coordenadas (ex: "São Paulo" ou "-23.53,-46.62"). Example: São Paulo
     *
     * @response 200 scenario="Clima atual" {
     *   "data": {
     *     "location": {
     *       "name": "Sao Paulo",
     *       "region": "Sao Paulo",
     *       "country": "Brazil",
     *       "lat": -23.53,
     *       "lon": -46.62,
     *       "tz_id": "America/Sao_Paulo",
     *       "localtime": "2026-01-18 11:30"
     *     },
     *     "current": {
     *       "temp_c": 28.0,
     *       "feelslike_c": 31.2,
     *       "humidity": 65,
     *       "wind_kph": 15.5,
     *       "wind_dir": "NE",
     *       "pressure_mb": 1015,
     *       "uv": 8,
     *       "is_day": true,
     *       "condition": {
     *         "text": "Parcialmente nublado",
     *         "icon": "https://cdn.weatherapi.com/weather/64x64/day/116.png",
     *         "code": 1003
     *       },
     *       "last_updated": "2026-01-18 11:15"
     *     }
     *   },
     *   "meta": {"success": true}
     * }
     *
     * @response 404 scenario="Local não encontrado" {
     *   "data": null,
     *   "meta": {"success": false},
     *   "errors": [{"code": "NOT_FOUND", "message": "Location not found"}]
     * }
     */
    public function current(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|max:100',
        ]);

        try {
            $result = $this->weatherService->current($request->q);

            return response()->json([
                'data' => $result,
                'meta' => ['success' => true],
                'errors' => null,
            ])->header('Cache-Control', 'public, max-age=300');

        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Previsão do Tempo
     *
     * Retorna previsão para os próximos dias (1-14).
     * Inclui clima atual, previsão por dia e por hora.
     *
     * @unauthenticated
     *
     * @queryParam q string required Cidade ou coordenadas. Example: São Paulo
     * @queryParam days integer Dias de previsão (1-14). Default: 3. Example: 3
     *
     * @response 200 scenario="Previsão 3 dias" {
     *   "data": {
     *     "location": {
     *       "name": "Sao Paulo",
     *       "region": "Sao Paulo",
     *       "country": "Brazil",
     *       "lat": -23.53,
     *       "lon": -46.62,
     *       "tz_id": "America/Sao_Paulo",
     *       "localtime": "2026-01-18 11:30"
     *     },
     *     "current": {
     *       "temp_c": 28.0,
     *       "feelslike_c": 31.2,
     *       "humidity": 65,
     *       "wind_kph": 15.5,
     *       "is_day": true,
     *       "condition": {
     *         "text": "Parcialmente nublado",
     *         "icon": "https://cdn.weatherapi.com/weather/64x64/day/116.png",
     *         "code": 1003
     *       }
     *     },
     *     "forecast": [
     *       {
     *         "date": "2026-01-18",
     *         "min_c": 20.0,
     *         "max_c": 32.0,
     *         "avg_temp_c": 26.0,
     *         "avg_humidity": 70,
     *         "chance_of_rain": 40,
     *         "uv": 9,
     *         "condition": {
     *           "text": "Possibilidade de chuva irregular",
     *           "icon": "https://cdn.weatherapi.com/weather/64x64/day/176.png",
     *           "code": 1180
     *         },
     *         "sunrise": "05:32 AM",
     *         "sunset": "07:15 PM",
     *         "hours": []
     *       }
     *     ]
     *   },
     *   "meta": {"success": true}
     * }
     */
    public function forecast(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|max:100',
            'days' => 'nullable|integer|min:1|max:14',
        ]);

        try {
            $days = $request->integer('days', 3);
            $result = $this->weatherService->forecast($request->q, $days);

            return response()->json([
                'data' => $result,
                'meta' => ['success' => true],
                'errors' => null,
            ])->header('Cache-Control', 'public, max-age=1800');

        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Handle service errors
     */
    protected function handleError(\Exception $e): JsonResponse
    {
        $status = $e->getCode();

        // Normalize status codes
        if ($status < 400 || $status >= 600) {
            $status = 500;
        }

        $errorCode = match ($status) {
            404 => 'NOT_FOUND',
            429 => 'RATE_LIMITED',
            401 => 'UNAUTHORIZED',
            default => 'WEATHER_ERROR',
        };

        return response()->json([
            'data' => null,
            'meta' => ['success' => false],
            'errors' => [
                [
                    'code' => $errorCode,
                    'message' => $e->getMessage(),
                ]
            ],
        ], $status);
    }
}
