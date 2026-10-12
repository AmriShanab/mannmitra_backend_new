<?php

namespace App\Domain\Exercises;

/**
 * Guided breathing: an intro, N breath cycles the user steps through with "Next", then a
 * closing mood check. Fully deterministic; makes no AI calls.
 */
class BreathingExercise implements Exercise
{
    public const ID = 'breathing_01';
    private const CYCLES = 3;

    // Seconds per phase (box breathing). Sent to the app so pacing is server-configurable.
    private const PATTERN = ['inhale' => 4, 'hold' => 4, 'exhale' => 4, 'rest' => 4];

    private const TEXT = [
        'en' => [
            'intro' => "Welcome to the Breathing Exercise. Let's begin by taking a deep breath in...",
            'cycles' => [
                "Alright, breathe in slowly through your nose... hold it gently... and let it all go.",
                "You're doing great. Let's fill those lungs again... hold... and slowly release.",
                "One more deep breath in... hold it... and let everything go.",
            ],
            'nudge' => "Take your time. Tap Next when you're ready for the next breath.",
            'done' => "You did so well. I hope you feel a little lighter now. How are you feeling?",
            'ready' => "I'm ready",
            'next' => "Next",
        ],
        'hi' => [
            'intro' => "Swagat hai. Chaliye ek gehri saans lene se shuru karte hain...",
            'cycles' => [
                "Theek hai, naak se dheere se saans andar lijiye... thoda rukiye... aur aaram se chhod dijiye.",
                "Bahut accha. Phir se gehri saans andar... rukiye... aur dheere se bahar.",
                "Ek aur gehri saans andar... rukiye... aur sab kuch chhod dijiye.",
            ],
            'nudge' => "Aaram se. Jab taiyaar ho jaayein to Next dabaiye.",
            'done' => "Aapne bahut accha kiya. Umeed hai ab thoda halka lag raha hoga. Ab aap kaisa mehsoos kar rahe hain?",
            'ready' => "Main taiyaar hoon",
            'next' => "Aage",
        ],
    ];

    public function id(): string
    {
        return self::ID;
    }

    public function start(string $lang): array
    {
        $t = self::TEXT[$lang] ?? self::TEXT['en'];
        $state = ['cycle' => 0];

        return [
            new ExerciseStep($t['intro'], 'breathing_animation', [$this->option($t['ready'])], false, 0, self::CYCLES, $this->meta()),
            $state,
        ];
    }

    public function advance(array $state, string $inputKind, string $text, string $lang): array
    {
        $t = self::TEXT[$lang] ?? self::TEXT['en'];
        $cycle = (int) ($state['cycle'] ?? 0);

        // Typing instead of tapping Next: stay on the same breath.
        if ($inputKind !== self::INPUT_NEXT) {
            return [
                new ExerciseStep($t['nudge'], 'breathing_animation', [$this->option($t['next'])], false, $cycle, self::CYCLES, $this->meta()),
                $state,
            ];
        }

        if ($cycle >= self::CYCLES) {
            $state['cycle'] = $cycle;
            return [new ExerciseStep($t['done'], 'emoji_slider', [], true, self::CYCLES, self::CYCLES), $state];
        }

        $state['cycle'] = $cycle + 1;
        $message = $t['cycles'][$cycle % count($t['cycles'])];

        return [
            new ExerciseStep($message, 'breathing_animation', [$this->option($t['next'])], false, $state['cycle'], self::CYCLES, $this->meta()),
            $state,
        ];
    }

    private function option(string $label): array
    {
        return ['id' => 'cbt_ready_' . self::ID, 'label' => $label];
    }

    private function meta(): array
    {
        return ['pattern' => self::PATTERN, 'cycles' => self::CYCLES];
    }
}
