<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\UpdateExperienceSearchJob;
use App\Jobs\AggregateMetricsJob;
use App\Jobs\CalculateTrendingJob;
use App\Jobs\AggregateEventsJob;
use App\Jobs\FetchWeatherJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Jobs
|--------------------------------------------------------------------------
*/

// Aggregate metrics from Redis to DB every minute
Schedule::job(new AggregateMetricsJob)->everyMinute();

// Update read model (experience_search) every minute
Schedule::job(new UpdateExperienceSearchJob)->everyMinute();

// Calculate trending per city every 5 minutes
Schedule::job(new CalculateTrendingJob)->everyFiveMinutes();

// Aggregate events_raw to experience_metrics_daily (daily at 2am)
Schedule::job(new AggregateEventsJob)->dailyAt('02:00');

// Fetch weather for all cities (every 3 hours)
Schedule::job(new FetchWeatherJob)->everyThreeHours();


