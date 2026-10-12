<?php

namespace App\Domain\Exercises;

/**
 * An exercise whose finished state is worth saving (e.g. a reframing thought record).
 */
interface ProducesRecord
{
    /** Eloquent model class the record is stored in (it gets user_id and exercise_run_id added). */
    public function recordModel(): string;

    /** Fields for that model, or null when the state is incomplete. */
    public function toRecord(array $state): ?array;
}
