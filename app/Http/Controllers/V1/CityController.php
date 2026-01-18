<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Support\CacheHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group 9. Cidades
 *
 * Endpoints para buscar e visualizar cidades disponíveis.
 *
 * Todas as experiências são vinculadas a uma cidade. Use estes endpoints
 * para buscar cidades disponíveis ou obter detalhes de uma cidade específica.
 */
class CityController extends Controller
{
    /**
     * Buscar Cidades
     *
     * Busca cidades por nome ou estado. Se nenhum termo for informado,
     * retorna as maiores cidades por população.
     *
     * @unauthenticated
     *
     * @queryParam q string Texto para buscar no nome ou estado. Example: São Paulo
     * @queryParam limit integer Quantidade máxima de resultados (1-50). Default: 10. Example: 10
     *
     * @response 200 scenario="Lista de cidades" {
     *   "data": [
     *     {
     *       "id": "edbca93c-2f01-4e17-af0a-53b1ccb4bf90",
     *       "name": "São Paulo",
     *       "slug": "sao-paulo",
     *       "state": "SP",
     *       "country": "BR",
     *       "lat": -23.5505,
     *       "lng": -46.6333,
     *       "timezone": "America/Sao_Paulo",
     *       "display_name": "São Paulo, SP"
     *     },
     *     {
     *       "id": "1dd3042f-077f-4721-b1c0-661c0976bfd2",
     *       "name": "Rio de Janeiro",
     *       "slug": "rio-de-janeiro",
     *       "state": "RJ",
     *       "country": "BR",
     *       "lat": -22.9068,
     *       "lng": -43.1729,
     *       "timezone": "America/Sao_Paulo",
     *       "display_name": "Rio de Janeiro, RJ"
     *     }
     *   ],
     *   "meta": {"success": true},
     *   "errors": null
     * }
     *
     * @responseField id string UUID da cidade.
     * @responseField name string Nome da cidade.
     * @responseField slug string Identificador URL-friendly.
     * @responseField state string Sigla do estado (UF).
     * @responseField country string Código do país (ISO 3166-1 alpha-2).
     * @responseField lat number Latitude do centro da cidade.
     * @responseField lng number Longitude do centro da cidade.
     * @responseField timezone string Fuso horário (IANA timezone).
     * @responseField display_name string Nome formatado para exibição.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $limit = $request->integer('limit', 10);
        $query = $request->get('q');

        $citiesQuery = City::query()->orderBy('population', 'desc');

        if ($query) {
            $citiesQuery->where('name', 'like', "%{$query}%")
                ->orWhere('state', 'like', "%{$query}%");
        }

        $cities = $citiesQuery->limit($limit)->get();

        return response()->json([
            'data' => $cities->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'state' => $c->state,
                'country' => $c->country,
                'lat' => (float) $c->lat,
                'lng' => (float) $c->lng,
                'timezone' => $c->timezone,
                'display_name' => "{$c->name}, {$c->state}",
            ]),
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Detalhes da Cidade
     *
     * Retorna informações detalhadas de uma cidade específica,
     * incluindo contagem de lugares e experiências disponíveis.
     *
     * @unauthenticated
     *
     * @urlParam id string required UUID da cidade. Example: edbca93c-2f01-4e17-af0a-53b1ccb4bf90
     *
     * @response 200 scenario="Detalhes da cidade" {
     *   "data": {
     *     "id": "edbca93c-2f01-4e17-af0a-53b1ccb4bf90",
     *     "name": "São Paulo",
     *     "slug": "sao-paulo",
     *     "state": "SP",
     *     "country": "BR",
     *     "lat": -23.5505,
     *     "lng": -46.6333,
     *     "timezone": "America/Sao_Paulo",
     *     "population": 12400000,
     *     "places_count": 150,
     *     "experiences_count": 320
     *   },
     *   "meta": {"success": true},
     *   "errors": null
     * }
     *
     * @response 404 scenario="Cidade não encontrada" {
     *   "data": null,
     *   "meta": {"success": false},
     *   "errors": [{"code": "NOT_FOUND", "message": "Cidade não encontrada"}]
     * }
     *
     * @responseField population integer População da cidade.
     * @responseField places_count integer Quantidade de lugares cadastrados.
     * @responseField experiences_count integer Quantidade de experiências disponíveis.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $city = CacheHelper::remember(
            "city:{$id}",
            3600,
            fn() => City::withCount(['places', 'experiences'])->findOrFail($id),
            ['cities']
        );

        return response()->json([
            'data' => [
                'id' => $city->id,
                'name' => $city->name,
                'slug' => $city->slug,
                'state' => $city->state,
                'country' => $city->country,
                'lat' => (float) $city->lat,
                'lng' => (float) $city->lng,
                'timezone' => $city->timezone,
                'population' => $city->population,
                'places_count' => $city->places_count ?? 0,
                'experiences_count' => $city->experiences_count ?? 0,
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }
}
