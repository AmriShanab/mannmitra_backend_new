<?php

namespace App\Domain\Exercises;

/**
 * An exercise whose finished state is worth saving (e.g. a reframing thought record).
 */
interface ProducesRecord
{
    /** Fields for a ThoughtRecord, or null when the state is incomplete. */
    public function toRecord(array $state): ?array;
}
