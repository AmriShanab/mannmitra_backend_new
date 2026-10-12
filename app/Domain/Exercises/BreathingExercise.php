<?php

namespace App\Domain\Exercises;

/**
 * Guided breathing: an intro, N breath cycles the user steps through with "Next", then a
 * closing mood check. The flow is deterministic. With EXERCISE_AI_ENABLED the opening, closing
 * and any message the user types mid-exercise get a short personalised line; otherwise no AI.
 */
class BreathingExercise implements Exercise
{
    public const ID = 'breathing_01';
    private const CYCLES = 3;
    private const NAME = 'Breathing';

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
            'ask' => "How are you feeling now?",
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
            'ask' => "Ab aap kaisa mehsoos kar rahe hain?",
            'ready' => "Main taiyaar hoon",
            'next' => "Aage",
        ],
    ];

    public function __construct(protected ExerciseVoice $voice)
    {
    }

    public function id(): string
    {
        return self::ID;
    }

    public function start(string $lang, array $context = []): array
    {
        $t = self::TEXT[$lang] ?? self::TEXT['en'];
        $open = $this->voice->line('open', self::NAME, ExerciseVoice::moodContext($context['mood'] ?? null), $lang);

        return [
            new ExerciseStep(ExerciseVoice::compose($open, $t['intro']), 'breathing_animation', [$this->option($t['ready'])], false, 0, self::CYCLES, $this->meta()),
            ['cycle' => 0],
        ];
    }

    public function advance(array $state, string $inputKind, string $text, string $lang): array
    {
        $t = self::TEXT[$lang] ?? self::TEXT['en'];
        $cycle = (int) ($state['cycle'] ?? 0);

        // Typing instead of tapping Next: answer what they said, then stay on the same breath.
        if ($inputKind !== self::INPUT_NEXT) {
            $reply = $this->voice->line('interrupt', self::NAME, 'They typed: "' . mb_substr($text, 0, 300) . '"', $lang);
            return [
                new ExerciseStep(ExerciseVoice::compose($reply, $t['nudge']), 'breathing_animation', [$this->option($t['next'])], false, $cycle, self::CYCLES, $this->meta()),
                $state,
            ];
        }

        if ($cycle >= self::CYCLES) {
            $close = $this->voice->line('close', self::NAME,
                'They finished ' . self::CYCLES . ' slow breaths. ' . ExerciseVoice::moodContext($state['mood_before'] ?? null), $lang);
            $message = $close ? $close . "\n\n" . $t['ask'] : $t['done'];

            return [new ExerciseStep($message, 'emoji_slider', [], true, self::CYCLES, self::CYCLES), $state];
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
