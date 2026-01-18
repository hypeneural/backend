<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\CityTrending;
use App\Models\ExperienceSearch;
use App\Models\Memory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    /**
     * Unified Home endpoint
     * GET /v1/home?city_id={id}&lat={lat}&lng={lng}
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'city_id' => 'required|uuid|exists:cities,id',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        $cityId = $request->city_id;
        $user = $request->user();

        // Update user location if provided
        if ($request->lat && $request->lng) {
            $user->update([
                'last_lat' => $request->lat,
                'last_lng' => $request->lng,
                'last_location_at' => now(),
            ]);
        }

        $userLat = $request->lat ?? $user->last_lat ?? -23.5505;
        $userLng = $request->lng ?? $user->last_lng ?? -46.6333;

        // Cache city data (shared across users)
        $cityData = Cache::tags(['city:' . $cityId, 'home'])
            ->remember("home:city:{$cityId}", 120, function () use ($cityId, $userLat, $userLng) {
                return $this->buildCityData($cityId, $userLat, $userLng);
            });

        // User-specific overlay (favorites set)
        $userFavoriteIds = $user->favorites()
            ->pluck('experience_id')
            ->toArray();

        // Mark saved experiences
        $highlight = collect($cityData['highlight'])->map(function ($exp) use ($userFavoriteIds) {
            $exp['is_saved'] = in_array($exp['id'], $userFavoriteIds);
            return $exp;
        })->all();

        $trending = collect($cityData['trending'])->map(function ($exp) use ($userFavoriteIds) {
            $exp['is_saved'] = in_array($exp['id'], $userFavoriteIds);
            return $exp;
        })->all();

        // Get user stats
        $stats = $user->stats;

        // Get recent memory
        $recentMemory = Memory::where('user_id', $user->id)
            ->orWhereIn('family_id', $user->families->pluck('id'))
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'data' => [
                'user' => [
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                    'family_name' => $user->getPrimaryFamily()?->name,
                    'streak' => $stats?->streak_days ?? 0,
                    'level' => $stats?->level ?? 1,
                ],
                'highlight' => $highlight,
                'trending' => $trending,
                'chips' => $cityData['chips'],
                'recent_memory' => $recentMemory ? [
                    'id' => $recentMemory->id,
                    'thumbnail_url' => $recentMemory->thumbnail_url,
                    'experience_title' => $recentMemory->experience?->title,
                ] : null,
            ],
            'meta' => [
                'success' => true,
                'cached_at' => now()->toISOString(),
            ],
            'errors' => null,
        ])->header('Cache-Control', 'private, max-age=120');
    }

    protected function buildCityData(string $cityId, float $lat, float $lng): array
    {
        // Get trending experiences
        $trending = CityTrending::where('city_id', $cityId)
            ->with('experience.category')
            ->orderBy('position')
            ->limit(10)
            ->get()
            ->map(fn($t) => $this->formatExperience($t->experience, $lat, $lng))
            ->all();

        // Get highlight (top 3-5 from trending or editor picks)
        $highlight = array_slice($trending, 0, 3);

        // Calculate chips (facet counts)
        $chips = [
            'adventure' => ExperienceSearch::where('city_id', $cityId)->where('status', 'published')
                ->whereRaw('(age_tags_mask & 4) > 0')->count(),
            'rain' => ExperienceSearch::where('city_id', $cityId)->where('status', 'published')
                ->whereRaw('(weather_mask & 2) > 0')->count(),
            'baby' => ExperienceSearch::where('city_id', $cityId)->where('status', 'published')
                ->whereRaw('(age_tags_mask & 1) > 0')->count(),
            'food' => ExperienceSearch::where('city_id', $cityId)->where('status', 'published')
                ->whereRaw('(practical_mask & 4) > 0')->count(),
        ];

        return [
            'highlight' => $highlight,
            'trending' => $trending,
            'chips' => $chips,
        ];
    }

    protected function formatExperience($experience, float $userLat, float $userLng): array
    {
        if (!$experience) {
            return [];
        }

        $search = $experience->searchRecord;
        $distance = $this->calculateDistance(
            $userLat,
            $userLng,
            (float) ($search?->lat ?? $experience->getResolvedLat()),
            (float) ($search?->lng ?? $experience->getResolvedLng())
        );

        return [
            'id' => $experience->id,
            'title' => $experience->title,
            'mission_title' => $experience->mission_title,
            'cover_image' => $experience->cover_image,
            'distance_km' => round($distance, 1),
            'price_level' => $experience->price_level,
            'average_rating' => $experience->average_rating,
            'reviews_count' => $experience->reviews_count,
            'category' => [
                'id' => $experience->category->id,
                'name' => $experience->category->name,
                'emoji' => $experience->category->emoji,
            ],
        ];
    }

    protected function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
