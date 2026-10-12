<?php

namespace App\Domain\Exercises;

/**
 * A guided exercise as a pure state machine. Progress lives in the $state array (stored on the
 * run), never in chat history. Both methods return [ExerciseStep, newState].
 */
interface Exercise
{
    public const INPUT_NEXT = 'next';
    public const INPUT_TEXT = 'text';
    public const INPUT_CHOICE = 'choice'; // a tapped option id

    /** The activity_id this exercise serves, e.g. "breathing_01". */
    public function id(): string;

    /** @return array{0: ExerciseStep, 1: array} */
    public function start(string $lang, array $context = []): array; // context: ['mood' => ?int]

    /** @return array{0: ExerciseStep, 1: array} */
    public function advance(array $state, string $inputKind, string $text, string $lang, array $context = []): array;
}
