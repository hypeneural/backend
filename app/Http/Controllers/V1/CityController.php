<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CityController extends Controller
{
    /**
     * Search/list cities
     * GET /v1/cities?q={query}&limit=10
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
     * Get city details
     * GET /v1/cities/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $city = Cache::tags(['cities'])
            ->remember("city:{$id}", 3600, function () use ($id) {
                return City::withCount(['places', 'experiences'])
                    ->findOrFail($id);
            });

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
