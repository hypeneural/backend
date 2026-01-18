<?php

namespace App\Jobs;

use App\Models\Experience;
use App\Models\ExperienceMetricsDaily;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class AggregateMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    /**
     * Aggregate metrics from Redis counters to database
     * Runs every 1 minute via scheduler
     */
    public function handle(): void
    {
        $today = now()->toDateString();

        // Get all experience metric keys
        $keys = Redis::keys('experience:metrics:*');

        foreach ($keys as $key) {
            // Extract experience ID from key
            preg_match('/experience:metrics:(.+)/', $key, $matches);
            $experienceId = $matches[1] ?? null;

            if (!$experienceId) {
                continue;
            }

            // Get all metrics for this experience
            $metrics = Redis::hgetall($key);

            if (empty($metrics)) {
                continue;
            }

            // Upsert daily metrics
            $this->upsertMetrics($experienceId, $today, $metrics);

            // Update denormalized counters on experience
            $this->updateExperienceCounters($experienceId, $metrics);

            // Clear processed metrics
            Redis::del($key);
        }
    }

    protected function upsertMetrics(string $experienceId, string $date, array $metrics): void
    {
        $existing = ExperienceMetricsDaily::where('experience_id', $experienceId)
            ->where('date', $date)
            ->first();

        if ($existing) {
            $existing->increment('saves', (int) ($metrics['saves'] ?? 0));
            $existing->increment('unsaves', (int) ($metrics['unsaves'] ?? 0));
            $existing->increment('views', (int) ($metrics['views'] ?? 0));
            $existing->increment('shares', (int) ($metrics['shares'] ?? 0));
            $existing->increment('clicks', (int) ($metrics['clicks'] ?? 0));
            $existing->increment('reviews', (int) ($metrics['reviews'] ?? 0));
            $existing->increment('plan_adds', (int) ($metrics['plan_adds'] ?? 0));
        } else {
            ExperienceMetricsDaily::create([
                'experience_id' => $experienceId,
                'date' => $date,
                'saves' => (int) ($metrics['saves'] ?? 0),
                'unsaves' => (int) ($metrics['unsaves'] ?? 0),
                'views' => (int) ($metrics['views'] ?? 0),
                'shares' => (int) ($metrics['shares'] ?? 0),
                'clicks' => (int) ($metrics['clicks'] ?? 0),
                'reviews' => (int) ($metrics['reviews'] ?? 0),
                'plan_adds' => (int) ($metrics['plan_adds'] ?? 0),
            ]);
        }
    }

    protected function updateExperienceCounters(string $experienceId, array $metrics): void
    {
        $netSaves = (int) ($metrics['saves'] ?? 0) - (int) ($metrics['unsaves'] ?? 0);

        if ($netSaves !== 0) {
            Experience::where('id', $experienceId)
                ->update([
                    'saves_count' => DB::raw("GREATEST(0, saves_count + {$netSaves})")
                ]);

            // Mark for read model update
            Redis::sadd('experience_search:dirty', $experienceId);
        }
    }
}
