<?php

namespace App\Domain\Exercises;

/**
 * An exercise whose finished state is worth saving (e.g. a reframing thought record).
 */
interface ProducesRecord
{
    /** The fields worth saving, or null when the state is incomplete. */
    public function toRecord(array $state): ?array;

    /** Stores what toRecord() returned for this user and run. */
    public function saveRecord(int $userId, int $runId, array $record): void;
}
