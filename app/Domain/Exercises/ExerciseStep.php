<?php

namespace App\Domain\Exercises;

/**
 * One screen of an exercise: what the companion says and which widget the app should show.
 */
class ExerciseStep
{
    public function __construct(
        public string $message,
        public string $uiMode,
        public array $options = [],
        public bool $completed = false,
        public int $progress = 0,
        public int $total = 0,
        public array $meta = [],
    ) {
    }
}
