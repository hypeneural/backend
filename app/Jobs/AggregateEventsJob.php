<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Aggregate Events Job
 * 
 * Aggregates raw events from events_raw into experience_metrics_daily.
 * Should run via scheduler every hour or daily.
 */
class AggregateEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Carbon $date;

    /**
     * Create a new job instance.
     */
    public function __construct(?Carbon $date = null)
    {
        $this->date = $date ?? Carbon::yesterday();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $dateStr = $this->date->toDateString();

        // Aggregate events by experience for the date
        $aggregates = DB::table('events_raw')
            ->select([
                'target_id as experience_id',
                DB::raw("DATE(created_at) as date"),
                DB::raw("SUM(CASE WHEN event = 'view' THEN 1 ELSE 0 END) as views"),
                DB::raw("SUM(CASE WHEN event = 'save' THEN 1 ELSE 0 END) as saves"),
                DB::raw("SUM(CASE WHEN event = 'unsave' THEN 1 ELSE 0 END) as unsaves"),
                DB::raw("SUM(CASE WHEN event = 'share' THEN 1 ELSE 0 END) as shares"),
                DB::raw("SUM(CASE WHEN event = 'plan_add' THEN 1 ELSE 0 END) as plan_adds"),
                DB::raw("SUM(CASE WHEN event = 'review' THEN 1 ELSE 0 END) as reviews"),
                DB::raw("SUM(CASE WHEN event = 'click' THEN 1 ELSE 0 END) as clicks"),
            ])
            ->where('target_type', 'experience')
            ->whereNotNull('target_id')
            ->whereDate('created_at', $dateStr)
            ->groupBy('target_id', DB::raw('DATE(created_at)'))
            ->get();

        foreach ($aggregates as $agg) {
            DB::table('experience_metrics_daily')->updateOrInsert(
                [
                    'experience_id' => $agg->experience_id,
                    'date' => $agg->date,
                ],
                [
                    'views' => $agg->views,
                    'saves' => $agg->saves,
                    'unsaves' => $agg->unsaves,
                    'shares' => $agg->shares,
                    'plan_adds' => $agg->plan_adds,
                    'reviews' => $agg->reviews,
                    'clicks' => $agg->clicks,
                    'updated_at' => now(),
                ]
            );
        }

        // Update trending scores based on recent metrics
        $this->updateTrendingScores();

        // Log completion
        info("AggregateEventsJob completed for {$dateStr}. Processed " . $aggregates->count() . " experiences.");
    }

    /**
     * Update trending scores based on recent activity
     */
    protected function updateTrendingScores(): void
    {
        // Calculate trending score for last 7 days
        $sevenDaysAgo = Carbon::now()->subDays(7)->toDateString();

        $trendingScores = DB::table('experience_metrics_daily')
            ->select([
                'experience_id',
                DB::raw("
                    SUM(views * 1) + 
                    SUM(saves * 5) + 
                    SUM(shares * 10) + 
                    SUM(plan_adds * 8) + 
                    SUM(reviews * 15) as score
                "),
            ])
            ->where('date', '>=', $sevenDaysAgo)
            ->groupBy('experience_id')
            ->get();

        foreach ($trendingScores as $item) {
            // Normalize score (0-100 scale)
            $normalizedScore = min(100, $item->score / 10);

            DB::table('experience_search')
                ->where('experience_id', $item->experience_id)
                ->update(['trending_score' => $normalizedScore]);
        }
    }
}
