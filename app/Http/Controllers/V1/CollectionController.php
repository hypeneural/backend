<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * @group 18. Utilidades
 *
 * Collections são listas curadas de experiências criadas pela equipe editorial.
 * Exemplos: "Dia de chuva", "Top 10 com bebê", "Baratinhos".
 */
class CollectionController extends Controller
{
    /**
     * Listar Collections
     *
     * Retorna collections curadas ativas para uma cidade.
     *
     * @unauthenticated
     *
     * @queryParam city_id string UUID da cidade (opcional, retorna globais + da cidade). Example: edbca93c-2f01-4e17-af0a-53b1ccb4bf90
     * @queryParam featured boolean Se true, retorna apenas collections em destaque. Example: true
     * @queryParam limit integer Quantidade máxima (1-20). Default: 10. Example: 10
     *
     * @response 200 scenario="Collections listadas" {
     *   "data": [
     *     {
     *       "id": "uuid",
     *       "name": "Dia de chuva",
     *       "slug": "dia-de-chuva",
     *       "emoji": "🌧️",
     *       "cover_image": "https://cdn.../rain.jpg",
     *       "description": "Experiências perfeitas para dias chuvosos",
     *       "experiences_count": 12,
     *       "type": "editorial"
     *     }
     *   ],
     *   "meta": {"success": true}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'city_id' => 'nullable|uuid|exists:cities,id',
            'featured' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $cityId = $request->city_id;
        $featured = $request->boolean('featured', false);
        $limit = $request->integer('limit', 10);

        $cacheKey = "collections:city:{$cityId}:featured:{$featured}:limit:{$limit}";

        $collections = Cache::remember($cacheKey, 3600, function () use ($cityId, $featured, $limit) {
            $query = Collection::query()
                ->where('is_active', true)
                ->withCount('items as experiences_count')
                ->orderBy('order');

            // Filter by city (null = global)
            if ($cityId) {
                $query->where(function ($q) use ($cityId) {
                    $q->whereNull('city_id')
                        ->orWhere('city_id', $cityId);
                });
            } else {
                $query->whereNull('city_id');
            }

            // Filter featured only
            if ($featured) {
                $query->where('is_featured', true);
            }

            // Filter by active date range (for seasonal)
            $query->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });

            return $query->limit($limit)->get();
        });

        return response()->json([
            'data' => $collections->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'emoji' => $c->emoji,
                'cover_image' => $c->cover_image,
                'description' => $c->description,
                'experiences_count' => $c->experiences_count ?? 0,
                'type' => $c->type,
            ]),
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Detalhes da Collection
     *
     * Retorna uma collection com suas experiências.
     *
     * @unauthenticated
     *
     * @urlParam id string required UUID ou slug da collection. Example: dia-de-chuva
     * @queryParam limit integer Experiências por página (1-50). Default: 20. Example: 20
     * @queryParam cursor string Cursor para paginação. No-example
     *
     * @response 200 scenario="Collection com experiências" {
     *   "data": {
     *     "id": "uuid",
     *     "name": "Dia de chuva",
     *     "slug": "dia-de-chuva",
     *     "emoji": "🌧️",
     *     "cover_image": "https://cdn.../rain.jpg",
     *     "description": "Experiências perfeitas para dias chuvosos",
     *     "experiences": [
     *       {
     *         "id": "uuid",
     *         "title": "Museu do Ipiranga",
     *         "custom_title": null,
     *         "cover_image": "https://...",
     *         "price_level": "moderate",
     *         "average_rating": 4.7,
     *         "category": {"id": "uuid", "name": "Museus", "emoji": "🏛️"}
     *       }
     *     ]
     *   },
     *   "meta": {"success": true, "next_cursor": null, "has_more": false}
     * }
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'cursor' => 'nullable|string',
        ]);

        $limit = $request->integer('limit', 20);

        // Find by UUID or slug
        $collection = Collection::where('id', $id)
            ->orWhere('slug', $id)
            ->where('is_active', true)
            ->firstOrFail();

        // Get experiences with pagination
        $itemsQuery = $collection->items()
            ->with(['experience.category'])
            ->orderBy('order');

        // Simple offset pagination for now
        $items = $itemsQuery->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        $items = $items->take($limit);

        return response()->json([
            'data' => [
                'id' => $collection->id,
                'name' => $collection->name,
                'slug' => $collection->slug,
                'emoji' => $collection->emoji,
                'cover_image' => $collection->cover_image,
                'description' => $collection->description,
                'experiences' => $items->map(fn($item) => [
                    'id' => $item->experience->id,
                    'title' => $item->experience->title,
                    'custom_title' => $item->custom_title,
                    'cover_image' => $item->experience->cover_image,
                    'price_level' => $item->experience->price_level,
                    'average_rating' => $item->experience->average_rating,
                    'category' => [
                        'id' => $item->experience->category->id,
                        'name' => $item->experience->category->name,
                        'emoji' => $item->experience->category->emoji,
                    ],
                ]),
            ],
            'meta' => [
                'success' => true,
                'has_more' => $hasMore,
            ],
            'errors' => null,
        ]);
    }
}
