<?php

namespace App\Jobs;

use App\Models\City;
use App\Models\CityTrending;
use App\Models\ExperienceMetricsDaily;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CalculateTrendingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    protected int $trendingLimit = 20;

    /**
     * Calculate trending experiences per city
     * Runs every 5 minutes via scheduler
     */
    public function handle(): void
    {
        $cities = City::all();

        foreach ($cities as $city) {
            $this->calculateTrendingForCity($city);
        }
    }

    protected function calculateTrendingForCity(City $city): void
    {
        // Calculate trending score based on recent activity
        // Formula: (saves * 3 + views * 1 + shares * 5 + reviews * 10) / days_decay
        $trending = DB::table('experience_metrics_daily as m')
            ->join('experiences as e', 'e.id', '=', 'm.experience_id')
            ->where('e.city_id', $city->id)
            ->where('e.status', 'published')
            ->where('m.date', '>=', now()->subDays(7))
            ->select([
                'm.experience_id',
                DB::raw('SUM(
                    (m.saves * 3 + m.views * 0.1 + m.shares * 5 + m.reviews * 10 + m.plan_adds * 4) 
                    / POWER(DATEDIFF(CURDATE(), m.date) + 1, 1.5)
                ) as score'),
            ])
            ->groupBy('m.experience_id')
            ->orderByDesc('score')
            ->limit($this->trendingLimit)
            ->get();

        // If no metrics, fall back to experiences with highest saves_count
        if ($trending->isEmpty()) {
            $trending = DB::table('experiences')
                ->where('city_id', $city->id)
                ->where('status', 'published')
                ->select(['id as experience_id', 'saves_count as score'])
                ->orderByDesc('saves_count')
                ->limit($this->trendingLimit)
                ->get();
        }

        // Clear existing trending for city
        CityTrending::where('city_id', $city->id)->delete();

        // Insert new trending
        $position = 1;
        foreach ($trending as $item) {
            CityTrending::create([
                'city_id' => $city->id,
                'experience_id' => $item->experience_id,
                'position' => $position,
                'score' => (float) $item->score,
                'calculated_at' => now(),
            ]);

            // Update trending_score on experience and search
            DB::table('experiences')
                ->where('id', $item->experience_id)
                ->update(['trending_score' => (float) $item->score]);

            DB::table('experience_search')
                ->where('experience_id', $item->experience_id)
                ->update(['trending_score' => (float) $item->score]);

            $position++;
        }

        // Invalidate trending cache for this city
        Cache::tags(['city:' . $city->id, 'trending'])->flush();
    }
}
