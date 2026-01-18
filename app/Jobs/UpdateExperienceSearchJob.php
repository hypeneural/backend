<?php

namespace App\Jobs;

use App\Models\Experience;
use App\Models\ExperienceSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class UpdateExperienceSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    /**
     * Process dirty experiences from Redis Set
     * This job is idempotent and uses debouncing
     */
    public function handle(): void
    {
        // Get dirty experience IDs from Redis (debounce set)
        $dirtyIds = Redis::smembers('experience_search:dirty');

        if (empty($dirtyIds)) {
            return;
        }

        // Process in batches
        $batches = array_chunk($dirtyIds, 50);

        foreach ($batches as $batch) {
            $this->processBatch($batch);
        }

        // Clear processed IDs from dirty set
        Redis::del('experience_search:dirty');
    }

    protected function processBatch(array $experienceIds): void
    {
        $experiences = Experience::with(['place', 'category'])
            ->whereIn('id', $experienceIds)
            ->get();

        foreach ($experiences as $experience) {
            $this->updateSearchRecord($experience);
        }
    }

    protected function updateSearchRecord(Experience $experience): void
    {
        $place = $experience->place;

        // Resolve coordinates (experience override or place)
        $lat = $experience->lat ?? $place->lat;
        $lng = $experience->lng ?? $place->lng;

        // Calculate duration bucket
        $avgDuration = ($experience->duration_min + $experience->duration_max) / 2;
        $durationBucket = match (true) {
            $avgDuration < 60 => 'quick',
            $avgDuration <= 180 => 'half',
            default => 'full',
        };

        // Calculate bitmasks
        $ageTagsMask = $this->calculateAgeMask($experience->age_tags ?? []);
        $weatherMask = $this->calculateWeatherMask($experience->weather ?? []);
        $practicalMask = $this->calculatePracticalMask($experience->practical ?? []);

        // Build search text
        $searchText = implode(' ', array_filter([
            $experience->title,
            $experience->mission_title,
            $experience->summary,
            $place->name,
            $place->neighborhood,
        ]));

        // Upsert using REPLACE INTO for idempotence
        DB::statement("
            REPLACE INTO experience_search (
                experience_id, title, mission_title, cover_image,
                category_id, city_id, lat, lng,
                price_level, duration_bucket,
                age_tags_mask, weather_mask, practical_mask,
                saves_count, reviews_count, average_rating, trending_score,
                search_text, status, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $experience->id,
            $experience->title,
            $experience->mission_title,
            $experience->cover_image,
            $experience->category_id,
            $experience->city_id,
            $lat,
            $lng,
            $experience->price_level,
            $durationBucket,
            $ageTagsMask,
            $weatherMask,
            $practicalMask,
            $experience->saves_count,
            $experience->reviews_count,
            $experience->average_rating,
            $experience->trending_score,
            $searchText,
            $experience->status,
            now(),
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

    protected function calculateWeatherMask(array $weather): int
    {
        $map = ['sun' => 1, 'rain' => 2, 'any' => 4];
        $mask = 0;
        foreach ($weather as $w) {
            $mask |= $map[$w] ?? 0;
        }
        return $mask;
    }

    protected function calculatePracticalMask(array $practical): int
    {
        $map = [
            'parking' => 1,
            'bathroom' => 2,
            'food' => 4,
            'stroller' => 8,
            'accessibility' => 16,
            'changing_table' => 32,
        ];
        $mask = 0;
        foreach ($practical as $key => $value) {
            if ($value && isset($map[$key])) {
                $mask |= $map[$key];
            }
        }
        return $mask;
    }
}
