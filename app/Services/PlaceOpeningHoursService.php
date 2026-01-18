<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Place Opening Hours Service
 * 
 * Manages place opening hours and "is_open_now" calculation.
 */
class PlaceOpeningHoursService
{
    /**
     * Check if a place is currently open
     */
    public function isOpenNow(string $placeId, ?string $timezone = null): bool
    {
        $timezone = $timezone ?? 'America/Sao_Paulo';
        $now = Carbon::now($timezone);
        $weekday = $now->dayOfWeek; // 0 = Sunday, 6 = Saturday
        $currentTime = $now->format('H:i:s');

        $hours = $this->getHoursForDay($placeId, $weekday);

        if (!$hours) {
            return true; // Assume open if no data
        }

        if ($hours->is_closed) {
            return false;
        }

        if ($hours->is_24h) {
            return true;
        }

        if (!$hours->open_time || !$hours->close_time) {
            return true;
        }

        return $currentTime >= $hours->open_time && $currentTime <= $hours->close_time;
    }

    /**
     * Get opening hours for a specific day
     */
    public function getHoursForDay(string $placeId, int $weekday): ?object
    {
        $cacheKey = "place:{$placeId}:hours:{$weekday}";

        return Cache::remember($cacheKey, 3600, function () use ($placeId, $weekday) {
            return DB::table('place_opening_hours')
                ->where('place_id', $placeId)
                ->where('weekday', $weekday)
                ->first();
        });
    }

    /**
     * Get full week schedule for a place
     */
    public function getWeekSchedule(string $placeId): array
    {
        $cacheKey = "place:{$placeId}:week_schedule";

        return Cache::remember($cacheKey, 3600, function () use ($placeId) {
            $hours = DB::table('place_opening_hours')
                ->where('place_id', $placeId)
                ->orderBy('weekday')
                ->get();

            $dayNames = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

            return $hours->map(fn($h) => [
                'weekday' => $h->weekday,
                'day_name' => $dayNames[$h->weekday],
                'is_closed' => $h->is_closed,
                'is_24h' => $h->is_24h,
                'open_time' => $h->open_time ? substr($h->open_time, 0, 5) : null,
                'close_time' => $h->close_time ? substr($h->close_time, 0, 5) : null,
                'display' => $this->formatHoursDisplay($h),
            ])->toArray();
        });
    }

    /**
     * Format hours for display
     */
    protected function formatHoursDisplay(object $hours): string
    {
        if ($hours->is_closed) {
            return 'Fechado';
        }

        if ($hours->is_24h) {
            return '24 horas';
        }

        if (!$hours->open_time || !$hours->close_time) {
            return 'Horário não informado';
        }

        $open = substr($hours->open_time, 0, 5);
        $close = substr($hours->close_time, 0, 5);

        return "{$open} - {$close}";
    }

    /**
     * Set opening hours for a place
     */
    public function setHours(
        string $placeId,
        int $weekday,
        ?string $openTime = null,
        ?string $closeTime = null,
        bool $isClosed = false,
        bool $is24h = false
    ): void {
        DB::table('place_opening_hours')->updateOrInsert(
            [
                'place_id' => $placeId,
                'weekday' => $weekday,
            ],
            [
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'open_time' => $openTime,
                'close_time' => $closeTime,
                'is_closed' => $isClosed,
                'is_24h' => $is24h,
                'updated_at' => now(),
            ]
        );

        // Clear cache
        Cache::forget("place:{$placeId}:hours:{$weekday}");
        Cache::forget("place:{$placeId}:week_schedule");
    }

    /**
     * Set typical business hours (Mon-Fri 9-18, Sat 9-13, Sun closed)
     */
    public function setTypicalBusinessHours(string $placeId): void
    {
        // Monday to Friday: 9am - 6pm
        for ($day = 1; $day <= 5; $day++) {
            $this->setHours($placeId, $day, '09:00:00', '18:00:00');
        }

        // Saturday: 9am - 1pm
        $this->setHours($placeId, 6, '09:00:00', '13:00:00');

        // Sunday: Closed
        $this->setHours($placeId, 0, null, null, isClosed: true);
    }

    /**
     * Set park hours (7am - 6pm every day)
     */
    public function setParkHours(string $placeId): void
    {
        for ($day = 0; $day <= 6; $day++) {
            $this->setHours($placeId, $day, '07:00:00', '18:00:00');
        }
    }

    /**
     * Set museum hours (Tue-Sun 10am - 5pm, Mon closed)
     */
    public function setMuseumHours(string $placeId): void
    {
        // Monday: Closed
        $this->setHours($placeId, 1, null, null, isClosed: true);

        // Tuesday to Sunday: 10am - 5pm
        foreach ([2, 3, 4, 5, 6, 0] as $day) {
            $this->setHours($placeId, $day, '10:00:00', '17:00:00');
        }
    }
}
