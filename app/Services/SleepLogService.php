<?php

namespace App\Services;

use App\Models\SleepLog;
use Carbon\Carbon;

/**
 * The simple sleep log: how long and how well the user slept. One entry per night; logging the
 * same night again replaces it. Used by the free log screen and by the Sleep Wind-down exercise.
 */
class SleepLogService
{
    private const MAX_BACKDATE_DAYS = 60;

    public function upsert(int $userId, string $date, float $hours, int $quality, ?array $checklist = null, ?int $runId = null): SleepLog
    {
        $values = array_filter([
            'hours' => round($hours * 2) / 2, // half-hour steps
            'quality' => $quality,
            'checklist' => $checklist,
            'exercise_run_id' => $runId,
        ], fn($v) => $v !== null);

        // Compare as dates: a date-cast column can be stored as a date-time on some databases.
        $existing = SleepLog::where('user_id', $userId)->whereDate('log_date', $date)->first();

        if ($existing) {
            $existing->update($values);
            return $existing;
        }

        return SleepLog::create($values + ['user_id' => $userId, 'log_date' => $date]);
    }

    public function loggedOn(int $userId, string $date): bool
    {
        return SleepLog::where('user_id', $userId)->whereDate('log_date', $date)->exists();
    }

    /** Whether a date is one a user may log: today or up to 60 days back, never the future. */
    public function validDate(string $date): bool
    {
        try {
            $d = Carbon::parse($date)->startOfDay();
        } catch (\Throwable $e) {
            return false;
        }
        return !$d->isFuture() && $d->gte(now()->subDays(self::MAX_BACKDATE_DAYS)->startOfDay());
    }

    /** Newest first, plus a 7-day summary. */
    public function overview(int $userId, int $limit = 30): array
    {
        $entries = SleepLog::where('user_id', $userId)->orderByDesc('log_date')->limit($limit)->get();
        $week = $entries->filter(fn($e) => $e->log_date->gte(now()->subDays(6)->startOfDay()));

        return [
            'entries' => $entries->map(fn($e) => [
                'date' => $e->log_date->toDateString(),
                'hours' => $e->hours,
                'quality' => $e->quality,
            ])->values()->all(),
            'summary' => [
                'entries_7d' => $week->count(),
                'avg_hours_7d' => $week->isEmpty() ? null : round($week->avg('hours'), 1),
                'avg_quality_7d' => $week->isEmpty() ? null : round($week->avg('quality'), 1),
                'logged_today' => $entries->contains(fn($e) => $e->log_date->isToday()),
            ],
        ];
    }
}
