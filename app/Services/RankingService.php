<?php

namespace App\Services;

use App\Models\Experience;
use App\Models\ExperienceSearch;
use App\Models\FamilyPreferenceCategory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Ranking Service
 * 
 * Implements hybrid ranking score for personalized feed:
 * score = trending_score * w1 + preference_match * w2 + distance_score * w3 + novelty * w4 + weather_fit * w5
 */
class RankingService
{
    // Weights for ranking factors
    protected array $weights = [
        'trending' => 0.25,
        'preference' => 0.30,
        'distance' => 0.20,
        'novelty' => 0.15,
        'weather' => 0.10,
    ];

    /**
     * Calculate personalized ranking score for experiences
     */
    public function rankExperiences(
        Collection $experiences,
        User $user,
        float $userLat,
        float $userLng,
        ?string $currentWeather = null
    ): Collection {
        // Get user preferences
        $preferenceWeights = $this->getUserPreferenceWeights($user);
        $savedExperienceIds = $this->getUserSavedExperienceIds($user);
        $viewedExperienceIds = $this->getUserViewedExperienceIds($user);

        return $experiences->map(function ($experience) use ($preferenceWeights, $savedExperienceIds, $viewedExperienceIds, $userLat, $userLng, $currentWeather) {
            $scores = [
                'trending' => $this->normalizeTrendingScore($experience->trending_score ?? 0),
                'preference' => $this->calculatePreferenceMatch($experience, $preferenceWeights),
                'distance' => $this->calculateDistanceScore($experience, $userLat, $userLng),
                'novelty' => $this->calculateNoveltyScore($experience, $savedExperienceIds, $viewedExperienceIds),
                'weather' => $this->calculateWeatherFit($experience, $currentWeather),
            ];

            $finalScore = 0;
            foreach ($scores as $factor => $score) {
                $finalScore += $score * $this->weights[$factor];
            }

            $experience->ranking_score = round($finalScore, 2);
            $experience->ranking_factors = $scores;

            return $experience;
        })->sortByDesc('ranking_score')->values();
    }

    /**
     * Get user's category preference weights
     */
    protected function getUserPreferenceWeights(User $user): array
    {
        $cacheKey = "user:{$user->id}:preference_weights";

        return Cache::remember($cacheKey, 3600, function () use ($user) {
            $family = $user->getPrimaryFamily();
            if (!$family) {
                return [];
            }

            return FamilyPreferenceCategory::where('family_id', $family->id)
                ->pluck('weight', 'category_id')
                ->toArray();
        });
    }

    /**
     * Get IDs of experiences user has saved
     */
    protected function getUserSavedExperienceIds(User $user): array
    {
        return Cache::remember("user:{$user->id}:saved_ids", 300, function () use ($user) {
            return $user->favorites()->pluck('experience_id')->toArray();
        });
    }

    /**
     * Get IDs of experiences user has viewed recently
     */
    protected function getUserViewedExperienceIds(User $user): array
    {
        // Could be enhanced with events_raw tracking
        return [];
    }

    /**
     * Normalize trending score to 0-100
     */
    protected function normalizeTrendingScore(float $score): float
    {
        return min(100, max(0, $score));
    }

    /**
     * Calculate preference match score based on category weights
     */
    protected function calculatePreferenceMatch($experience, array $preferenceWeights): float
    {
        if (empty($preferenceWeights)) {
            return 50; // Neutral score if no preferences
        }

        $categoryId = $experience->category_id ?? $experience->category?->id;

        if (!$categoryId || !isset($preferenceWeights[$categoryId])) {
            return 30; // Lower score for non-preferred categories
        }

        // Weight is typically 0.0 to 1.0, convert to 0-100
        return $preferenceWeights[$categoryId] * 100;
    }

    /**
     * Calculate distance score (closer = higher)
     */
    protected function calculateDistanceScore($experience, float $userLat, float $userLng): float
    {
        $expLat = $experience->lat ?? $experience->coords['lat'] ?? null;
        $expLng = $experience->lng ?? $experience->coords['lng'] ?? null;

        if (!$expLat || !$expLng) {
            return 50; // Neutral if no coords
        }

        $distance = $this->haversineDistance($userLat, $userLng, $expLat, $expLng);

        // Score: 100 for < 1km, decreasing to 0 at 50km+
        if ($distance < 1)
            return 100;
        if ($distance >= 50)
            return 0;

        return 100 - ($distance * 2);
    }

    /**
     * Calculate novelty score (penalize already saved/viewed)
     */
    protected function calculateNoveltyScore($experience, array $savedIds, array $viewedIds): float
    {
        $id = $experience->id ?? $experience->experience_id;

        if (in_array($id, $savedIds)) {
            return 20; // Already saved - lower priority in discovery
        }

        if (in_array($id, $viewedIds)) {
            return 50; // Viewed but not saved - medium priority
        }

        return 100; // Fresh content - high priority
    }

    /**
     * Calculate weather fitness score
     */
    protected function calculateWeatherFit($experience, ?string $currentWeather): float
    {
        if (!$currentWeather) {
            return 50; // Neutral if weather unknown
        }

        $weatherMask = $experience->weather_mask ?? 0;

        // Weather masks: sun=1, rain=2, any=4
        $weatherMap = ['sun' => 1, 'rain' => 2, 'any' => 4];
        $currentBit = $weatherMap[$currentWeather] ?? 0;

        // Check if experience supports current weather
        if (($weatherMask & $currentBit) > 0 || ($weatherMask & 4) > 0) {
            return 100; // Perfect match
        }

        // If raining and experience is outdoor-only
        if ($currentWeather === 'rain' && ($weatherMask & 1) > 0 && ($weatherMask & 2) === 0) {
            return 10; // Bad match
        }

        return 50; // Neutral
    }

    /**
     * Calculate Haversine distance in km
     */
    protected function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
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
