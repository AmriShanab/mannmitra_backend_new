<?php

namespace App\Domain\Exercises;

use App\Models\ExerciseRun;
use App\Models\ThoughtRecord;
use Carbon\Carbon;

/**
 * Read-side summary of a user's finished exercises: totals, streak, week view and mood change.
 */
class ExerciseProgressService
{
    private const LOOKBACK_DAYS = 60;

    public function summary($user): array
    {
        $runs = ExerciseRun::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays(self::LOOKBACK_DAYS)->startOfDay())
            ->get(['activity_id', 'completed_at', 'state']);

        $total = ExerciseRun::where('user_id', $user->id)->where('status', 'completed')->count();

        $days = $runs->map(fn($r) => $r->completed_at->toDateString())->unique()->values();

        return [
            'total_completed' => $total,
            'by_exercise' => $runs->groupBy('activity_id')->map->count()->all(),
            'streak_days' => $this->streak($days->all()),
            'last_7_days' => $this->week($days->all()),
            'avg_mood_change' => $this->avgMoodChange($runs),
        ];
    }

    public function thoughtRecords($user, int $limit = 20)
    {
        return ThoughtRecord::where('user_id', $user->id)->latest()->limit($limit)->get();
    }

    /** Consecutive days ending today (or yesterday, so an unfinished today keeps the streak). */
    private function streak(array $dates): int
    {
        $set = array_flip($dates);
        $cursor = Carbon::today();

        if (!isset($set[$cursor->toDateString()])) {
            $cursor = $cursor->subDay();
        }

        $streak = 0;
        while (isset($set[$cursor->toDateString()])) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    private function week(array $dates): array
    {
        $set = array_flip($dates);
        $out = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i)->toDateString();
            $out[] = ['date' => $day, 'done' => isset($set[$day])];
        }
        return $out;
    }

    /** Average (after - before) mood score over runs that recorded both; null when none did. */
    private function avgMoodChange($runs): ?float
    {
        $changes = $runs->map(fn($r) => (isset($r->state['mood_before'], $r->state['mood_after']))
            ? $r->state['mood_after'] - $r->state['mood_before']
            : null)->filter(fn($v) => $v !== null);

        return $changes->isEmpty() ? null : round($changes->avg(), 1);
    }
}
