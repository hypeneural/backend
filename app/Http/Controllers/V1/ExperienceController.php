<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Experience;
use App\Support\CacheHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExperienceController extends Controller
{
    /**
     * Get experience details
     * GET /v1/experiences/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $userLat = $user->last_lat ?? -23.5505;
        $userLng = $user->last_lng ?? -46.6333;

        // Cache experience data (10 min, with optional tag invalidation)
        $experience = CacheHelper::remember(
            "experience:detail:{$id}",
            600,
            fn() => Experience::with([
                'category',
                'place.city',
                'reviews' => function ($q) {
                    $q->public()->orderByDesc('helpful_count')->limit(5);
                },
                'reviews.user'
            ])
                ->where('status', 'published')
                ->findOrFail($id),
            ['experience:' . $id]
        );

        // Check if saved by user
        $isSaved = $user->favorites()->where('experience_id', $id)->exists();

        // Calculate distance
        $expLat = $experience->getResolvedLat();
        $expLng = $experience->getResolvedLng();
        $distance = $this->calculateDistance($userLat, $userLng, $expLat, $expLng);

        // Format review stats
        $reviewStats = $this->getReviewStats($experience);

        return response()->json([
            'data' => [
                'id' => $experience->id,
                'title' => $experience->title,
                'mission_title' => $experience->mission_title,
                'summary' => $experience->summary,
                'category' => [
                    'id' => $experience->category->id,
                    'name' => $experience->category->name,
                    'emoji' => $experience->category->emoji,
                    'color' => $experience->category->color,
                ],
                'badges' => $experience->badges ?? [],
                'age_tags' => $experience->age_tags ?? [],
                'vibe' => $experience->vibe ?? [],
                'duration' => [
                    'label' => $this->formatDuration($experience->duration_min, $experience->duration_max),
                    'minutes_min' => $experience->duration_min,
                    'minutes_max' => $experience->duration_max,
                ],
                'price' => [
                    'level' => $experience->price_level,
                    'label' => $experience->price_label ?? $this->getPriceLabel($experience->price_level),
                ],
                'weather' => $experience->weather ?? [],
                'practical' => $experience->practical ?? [],
                'tips' => $experience->tips ?? [],
                'location' => [
                    'name' => $experience->place->name,
                    'address' => $experience->place->address,
                    'city' => $experience->place->city->name,
                    'state' => $experience->place->city->state,
                    'neighborhood' => $experience->place->neighborhood,
                ],
                'coords' => [
                    'lat' => $expLat,
                    'lng' => $expLng,
                ],
                'images' => [
                    'cover' => $experience->cover_image,
                    'gallery' => $experience->gallery ?? [],
                ],
                'stats' => [
                    'saves_count' => $experience->saves_count,
                    'reviews_count' => $experience->reviews_count,
                    'average_rating' => $experience->average_rating,
                ],
                'review_stats' => $reviewStats,
                'recent_reviews' => $experience->reviews->map(fn($r) => [
                    'id' => $r->id,
                    'user_name' => $r->user->name,
                    'user_avatar' => $r->user->avatar,
                    'rating' => $r->rating,
                    'comment' => $r->comment,
                    'created_at' => $r->created_at?->toISOString(),
                ]),
                'is_saved' => $isSaved,
                'distance_km' => round($distance, 1),
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ])->header('Cache-Control', 'private, max-age=600')
            ->header('ETag', md5($experience->updated_at . $isSaved));
    }

    protected function getReviewStats(Experience $experience): array
    {
        // Get rating distribution
        $distribution = $experience->reviews()
            ->public()
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        return [
            'distribution' => [
                '1' => $distribution[1] ?? 0,
                '2' => $distribution[2] ?? 0,
                '3' => $distribution[3] ?? 0,
                '4' => $distribution[4] ?? 0,
                '5' => $distribution[5] ?? 0,
            ],
        ];
    }

    protected function formatDuration(int $min, int $max): string
    {
        if ($max < 60) {
            return "{$min}-{$max}min";
        }

        $minHours = floor($min / 60);
        $maxHours = ceil($max / 60);

        if ($minHours == $maxHours) {
            return "{$minHours}h";
        }

        return "{$minHours}-{$maxHours}h";
    }

    protected function getPriceLabel(string $level): string
    {
        return match ($level) {
            'free' => 'Grátis',
            'moderate' => 'Moderado',
            'top' => 'Premium',
            default => $level,
        };
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
