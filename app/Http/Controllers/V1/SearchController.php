<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\ExperienceSearch;
use App\Support\CursorPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class SearchController extends Controller
{
    /**
     * Search experiences with facets and cursor pagination
     * GET /v1/experiences/search
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'city_id' => 'required|uuid|exists:cities,id',
            'q' => 'nullable|string|max:200',
            'categories' => 'nullable|array',
            'categories.*' => 'uuid',
            'price' => 'nullable|array',
            'price.*' => 'in:free,moderate,top',
            'duration' => 'nullable|in:quick,half,full',
            'age_tags' => 'nullable|array',
            'age_tags.*' => 'in:baby,toddler,kid,teen,all',
            'weather' => 'nullable|in:sun,rain,any',
            'cursor' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $cityId = $request->city_id;
        $limit = $request->integer('limit', 20);
        $user = $request->user();
        $userLat = $user->last_lat ?? -23.5505;
        $userLng = $user->last_lng ?? -46.6333;

        // Build query
        $query = ExperienceSearch::query()
            ->where('city_id', $cityId)
            ->where('status', 'published');

        // Full-text search
        if ($request->filled('q')) {
            $query->search($request->q);
        }

        // Category filter
        if ($request->filled('categories')) {
            $query->whereIn('category_id', $request->categories);
        }

        // Price filter
        if ($request->filled('price')) {
            $query->whereIn('price_level', $request->price);
        }

        // Duration filter
        if ($request->filled('duration')) {
            $query->where('duration_bucket', $request->duration);
        }

        // Age tags filter (bitmask)
        if ($request->filled('age_tags')) {
            $mask = $this->calculateAgeMask($request->age_tags);
            $query->withAgeTag($mask);
        }

        // Weather filter (bitmask)
        if ($request->filled('weather')) {
            $weatherMap = ['sun' => 1, 'rain' => 2, 'any' => 4];
            $mask = $weatherMap[$request->weather] ?? 0;
            $query->withWeather($mask);
        }

        // Cursor pagination (by trending_score DESC, experience_id DESC)
        if ($request->filled('cursor')) {
            $cursor = CursorPaginator::decodeCursor($request->cursor);
            if ($cursor) {
                $query->where(function ($q) use ($cursor) {
                    $q->where('trending_score', '<', $cursor['score'])
                        ->orWhere(function ($q2) use ($cursor) {
                            $q2->where('trending_score', '=', $cursor['score'])
                                ->where('experience_id', '<', $cursor['id']);
                        });
                });
            }
        }

        // Order and fetch
        $results = $query->orderByDesc('trending_score')
            ->orderByDesc('experience_id')
            ->limit($limit + 1)
            ->get();

        // Check if there's a next page
        $hasMore = $results->count() > $limit;
        if ($hasMore) {
            $results = $results->take($limit);
        }

        // Get user favorites for isSaved flag
        $favoriteIds = $user->favorites()->pluck('experience_id')->toArray();

        // Format results
        $formattedResults = $results->map(function ($exp) use ($userLat, $userLng, $favoriteIds) {
            return $this->formatExperience($exp, $userLat, $userLng, $favoriteIds);
        });

        // Build next cursor
        $nextCursor = null;
        if ($hasMore && $results->isNotEmpty()) {
            $lastItem = $results->last();
            $nextCursor = CursorPaginator::encodeCursor([
                'score' => $lastItem->trending_score,
                'id' => $lastItem->experience_id,
            ]);
        }

        // Get facets (cached per city + filter hash)
        $filterHash = md5(json_encode($request->only(['categories', 'price', 'duration', 'age_tags', 'weather'])));
        $facets = Cache::tags(['city:' . $cityId, 'facets'])
            ->remember("facets:{$cityId}:{$filterHash}", 120, function () use ($cityId) {
                return $this->calculateFacets($cityId);
            });

        return response()->json([
            'data' => [
                'results' => $formattedResults,
                'facets' => $facets,
                'applied_filters' => $request->only(['categories', 'price', 'duration', 'age_tags', 'weather']),
            ],
            'meta' => [
                'success' => true,
                'next_cursor' => $nextCursor,
                'has_more' => $hasMore,
            ],
            'errors' => null,
        ]);
    }

    protected function calculateAgeMask(array $tags): int
    {
        $map = ['baby' => 1, 'toddler' => 2, 'kid' => 4, 'teen' => 8, 'all' => 16];
        $mask = 0;
        foreach ($tags as $tag) {
            $mask |= $map[$tag] ?? 0;
        }
        return $mask;
    }

    protected function calculateFacets(string $cityId): array
    {
        $base = ExperienceSearch::where('city_id', $cityId)->where('status', 'published');

        // Categories
        $categories = (clone $base)
            ->selectRaw('category_id, COUNT(*) as count')
            ->groupBy('category_id')
            ->with('category:id,name,emoji')
            ->get()
            ->map(fn($r) => [
                'id' => $r->category_id,
                'name' => $r->category?->name,
                'emoji' => $r->category?->emoji,
                'count' => $r->count,
            ]);

        // Price levels
        $priceLevel = (clone $base)
            ->selectRaw('price_level, COUNT(*) as count')
            ->groupBy('price_level')
            ->get()
            ->map(fn($r) => [
                'value' => $r->price_level,
                'count' => $r->count,
            ]);

        // Age tags (approximate)
        $ageTags = [
            ['value' => 'baby', 'count' => (clone $base)->whereRaw('(age_tags_mask & 1) > 0')->count()],
            ['value' => 'toddler', 'count' => (clone $base)->whereRaw('(age_tags_mask & 2) > 0')->count()],
            ['value' => 'kid', 'count' => (clone $base)->whereRaw('(age_tags_mask & 4) > 0')->count()],
            ['value' => 'teen', 'count' => (clone $base)->whereRaw('(age_tags_mask & 8) > 0')->count()],
        ];

        return [
            'categories' => $categories,
            'price_level' => $priceLevel,
            'age_tags' => $ageTags,
        ];
    }

    protected function formatExperience($exp, float $userLat, float $userLng, array $favoriteIds): array
    {
        $distance = $this->calculateDistance($userLat, $userLng, (float) $exp->lat, (float) $exp->lng);

        return [
            'id' => $exp->experience_id,
            'title' => $exp->title,
            'mission_title' => $exp->mission_title,
            'cover_image' => $exp->cover_image,
            'distance_km' => round($distance, 1),
            'price_level' => $exp->price_level,
            'duration_bucket' => $exp->duration_bucket,
            'average_rating' => $exp->average_rating,
            'reviews_count' => $exp->reviews_count,
            'saves_count' => $exp->saves_count,
            'is_saved' => in_array($exp->experience_id, $favoriteIds),
        ];
    }

    protected function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
