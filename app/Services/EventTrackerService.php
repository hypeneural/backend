<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

/**
 * Event Tracker Service
 * 
 * Tracks user events for analytics without overwhelming the database.
 * Events are stored in append-only table for later aggregation.
 */
class EventTrackerService
{
    /**
     * Track an event
     */
    public function track(
        string $event,
        ?string $userId = null,
        ?string $targetType = null,
        ?string $targetId = null,
        ?string $cityId = null,
        ?string $source = null,
        array $meta = [],
        ?Request $request = null
    ): void {
        DB::table('events_raw')->insert([
            'user_id' => $userId,
            'event' => $event,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'city_id' => $cityId,
            'source' => $source,
            'meta' => !empty($meta) ? json_encode($meta) : null,
            'ip_hash' => $request ? hash('sha256', $request->ip()) : null,
            'ua_hash' => $request ? hash('sha256', $request->userAgent() ?? '') : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Track experience view
     */
    public function trackView(string $experienceId, ?string $userId, ?string $cityId, string $source, Request $request): void
    {
        $this->track('view', $userId, 'experience', $experienceId, $cityId, $source, [], $request);
    }

    /**
     * Track experience save
     */
    public function trackSave(string $experienceId, ?string $userId, ?string $cityId, Request $request): void
    {
        $this->track('save', $userId, 'experience', $experienceId, $cityId, 'app', [], $request);
    }

    /**
     * Track experience unsave
     */
    public function trackUnsave(string $experienceId, ?string $userId, Request $request): void
    {
        $this->track('unsave', $userId, 'experience', $experienceId, null, 'app', [], $request);
    }

    /**
     * Track share
     */
    public function trackShare(string $targetType, string $targetId, ?string $userId, Request $request): void
    {
        $this->track('share', $userId, $targetType, $targetId, null, 'app', [], $request);
    }

    /**
     * Track search
     */
    public function trackSearch(?string $userId, string $cityId, array $filters, int $resultsCount, Request $request): void
    {
        $this->track('search', $userId, null, null, $cityId, 'search', [
            'filters' => $filters,
            'results_count' => $resultsCount,
        ], $request);
    }

    /**
     * Track plan add
     */
    public function trackPlanAdd(string $experienceId, string $planId, ?string $userId, Request $request): void
    {
        $this->track('plan_add', $userId, 'experience', $experienceId, null, 'app', [
            'plan_id' => $planId,
        ], $request);
    }

    /**
     * Track review creation
     */
    public function trackReview(string $experienceId, ?string $userId, int $rating, Request $request): void
    {
        $this->track('review', $userId, 'experience', $experienceId, null, 'app', [
            'rating' => $rating,
        ], $request);
    }

    /**
     * Track memory creation
     */
    public function trackMemory(string $memoryId, ?string $userId, ?string $experienceId, Request $request): void
    {
        $this->track('memory', $userId, 'memory', $memoryId, null, 'app', [
            'experience_id' => $experienceId,
        ], $request);
    }
}
