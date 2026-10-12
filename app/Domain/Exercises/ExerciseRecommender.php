<?php

namespace App\Domain\Exercises;

use App\Models\ExerciseRun;
use App\Models\MoodEntry;

/**
 * Picks one exercise to highlight. Deterministic rules, no AI:
 *  - low recent mood (<=4 in the last 24h): breathing first, grounding if breathing was already done today
 *  - otherwise: night-time favours breathing; else the exercise done least recently (or never).
 */
class ExerciseRecommender
{
    private const LOW_MOOD = 4;

    public function pick($user, array $activityIds): ?string
    {
        if ($activityIds === []) {
            return null;
        }

        $lastDone = ExerciseRun::where('user_id', $user->id)
            ->where('status', 'completed')
            ->selectRaw('activity_id, MAX(completed_at) as last_at')
            ->groupBy('activity_id')
            ->pluck('last_at', 'activity_id');

        $mood = MoodEntry::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDay())
            ->latest('id')
            ->value('primary_mood');

        $doneToday = fn(string $id) => isset($lastDone[$id]) && \Carbon\Carbon::parse($lastDone[$id])->isToday();
        $has = fn(string $id) => in_array($id, $activityIds, true);

        if (is_numeric($mood) && (int) $mood <= self::LOW_MOOD) {
            if ($has(BreathingExercise::ID) && !$doneToday(BreathingExercise::ID)) {
                return BreathingExercise::ID;
            }
            if ($has(GroundingExercise::ID) && !$doneToday(GroundingExercise::ID)) {
                return GroundingExercise::ID;
            }
            if ($has(ButterflyHugExercise::ID) && !$doneToday(ButterflyHugExercise::ID)) {
                return ButterflyHugExercise::ID;
            }
        }

        $hour = (int) now()->format('G');
        if ($hour >= 21 || $hour < 5) {
            if ($has(SleepWinddownExercise::ID) && !$doneToday(SleepWinddownExercise::ID)) {
                return SleepWinddownExercise::ID;
            }
            if ($has(BreathingExercise::ID) && !$doneToday(BreathingExercise::ID)) {
                return BreathingExercise::ID;
            }
        }

        // Early evening is the natural time to look back on the day.
        if ($hour >= 17 && $hour < 21 && $has(GratitudeExercise::ID) && !$doneToday(GratitudeExercise::ID)) {
            return GratitudeExercise::ID;
        }

        // Never done first, then oldest completion.
        usort($activityIds, fn($a, $b) => strcmp((string) ($lastDone[$a] ?? ''), (string) ($lastDone[$b] ?? '')));

        return $activityIds[0];
    }
}
