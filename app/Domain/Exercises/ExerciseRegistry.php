<?php

namespace App\Domain\Exercises;

/**
 * Maps activity_id to its state-machine exercise. Adding an exercise = a new class + one entry.
 */
class ExerciseRegistry
{
    /** @var array<string, class-string<Exercise>> */
    private const EXERCISES = [
        BreathingExercise::ID => BreathingExercise::class,
        GroundingExercise::ID => GroundingExercise::class,
        ReframingExercise::ID => ReframingExercise::class,
        ButterflyHugExercise::ID => ButterflyHugExercise::class,
        GratitudeExercise::ID => GratitudeExercise::class,
        SleepWinddownExercise::ID => SleepWinddownExercise::class,
    ];

    public function has(string $activityId): bool
    {
        return isset(self::EXERCISES[$activityId]);
    }

    public function get(string $activityId): ?Exercise
    {
        return $this->has($activityId) ? app(self::EXERCISES[$activityId]) : null;
    }
}
