<?php

namespace App\Domain\Exercises;

/**
 * Butterfly Hug: cross the arms over the chest and tap left/right alternately while breathing
 * slowly, for one minute. The flow is deterministic. With EXERCISE_AI_ENABLED the opening,
 * closing and any message typed during the minute get a short personalised line; otherwise no AI.
 *
 * Steps: 0 = instructions, 1 = the timed minute (the app animates and times it), 2 = done.
 * The server records when the minute started and only completes once it has really elapsed, so a
 * paid session cannot be used up by tapping straight through.
 */
class ButterflyHugExercise implements Exercise
{
    public const ID = 'butterfly_hug_01';
    private const NAME = 'Butterfly Hug';

    private const DURATION_SECONDS = 60;
    private const TAP_MS = 1200;      // one tap (left or right) every 1.2 s
    private const BREATH_SECONDS = 8; // 4 in + 4 out
    private const GRACE_SECONDS = 5;  // tolerate network/UI lag at the end
    private const TOTAL = 2;

    private const TEXT = [
        'en' => [
            'intro' => "Let's try the Butterfly Hug. Cross your arms over your chest, with your hands resting near your collarbones like a butterfly's wings. Then gently tap left, right, left, right, while breathing slowly. We'll do this for one minute. Tap \"I'm ready\" when you want to begin.",
            'active' => "Tap left, right, left, right, slowly. Breathe in through your nose and out through your mouth. Let your breathing settle. I'll let you know when the minute is up.",
            'early' => "Stay with it a little longer, you're doing well. The button will light up when the minute is complete.",
            'done' => "Well done. Gently let your arms rest. How are you feeling now?",
            'ask' => "Gently let your arms rest. How are you feeling now?",
            'ready' => "I'm ready",
            'finish' => "Done",
        ],
        'hi' => [
            'intro' => "Chaliye Butterfly Hug karte hain. Apne haath seene par cross karke rakhiye, ungliyan collarbone ke paas, jaise titli ke pankh. Phir dheere se baayein, daayein, baayein, daayein tap kijiye, aur saath mein dheere saans lijiye. Hum yeh ek minute ke liye karenge. Shuru karne ke liye \"Main taiyaar hoon\" dabaiye.",
            'active' => "Dheere dheere baayein, daayein, baayein, daayein tap kijiye. Naak se saans andar, munh se bahar. Apni saans ko sthir hone dijiye. Ek minute poora hone par main bata dunga.",
            'early' => "Thoda aur rukiye, aap accha kar rahe hain. Ek minute poora hone par button chalu ho jayega.",
            'done' => "Bahut accha. Ab apne haath aaram se neeche kar lijiye. Ab aap kaisa mehsoos kar rahe hain?",
            'ask' => "Ab apne haath aaram se neeche kar lijiye. Ab aap kaisa mehsoos kar rahe hain?",
            'ready' => "Main taiyaar hoon",
            'finish' => "Ho gaya",
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
        $t = $this->t($lang);
        $open = $this->voice->line('open', self::NAME, ExerciseVoice::moodContext($context['mood'] ?? null), $lang);

        return [
            new ExerciseStep(ExerciseVoice::compose($open, $t['intro']), 'buttons', [$this->option($t['ready'])], false, 0, self::TOTAL),
            ['phase' => 'intro'],
        ];
    }

    public function advance(array $state, string $inputKind, string $text, string $lang): array
    {
        $t = $this->t($lang);
        $phase = $state['phase'] ?? 'intro';

        // Typing instead of tapping: answer what they said, then repeat the current screen.
        if ($inputKind !== self::INPUT_NEXT) {
            $reply = $this->voice->line('interrupt', self::NAME, 'They typed: "' . mb_substr($text, 0, 300) . '"', $lang);

            return $phase === 'active'
                ? [$this->activeStep(ExerciseVoice::compose($reply, $t['early']), $t), $state]
                : [new ExerciseStep(ExerciseVoice::compose($reply, $t['intro']), 'buttons', [$this->option($t['ready'])], false, 0, self::TOTAL), $state];
        }

        if ($phase === 'intro') {
            $state = ['phase' => 'active', 'started_at' => now()->timestamp, 'mood_before' => $state['mood_before'] ?? null];
            return [$this->activeStep($t['active'], $t), $state];
        }

        // Active: only finish once the full minute has passed.
        $elapsed = now()->timestamp - (int) ($state['started_at'] ?? 0);
        if ($elapsed < self::DURATION_SECONDS - self::GRACE_SECONDS) {
            return [$this->activeStep($t['early'], $t), $state];
        }

        $state['phase'] = 'done';
        $close = $this->voice->line('close', self::NAME,
            'They completed a full minute of the Butterfly Hug. ' . ExerciseVoice::moodContext($state['mood_before'] ?? null), $lang);
        $message = $close ? $close . "\n\n" . $t['ask'] : $t['done'];

        return [new ExerciseStep($message, 'emoji_slider', [], true, self::TOTAL, self::TOTAL), $state];
    }

    private function activeStep(string $message, array $t): ExerciseStep
    {
        return new ExerciseStep(
            $message,
            'butterfly_hug',
            [$this->option($t['finish'])],
            false,
            1,
            self::TOTAL,
            ['duration_seconds' => self::DURATION_SECONDS, 'tap_ms' => self::TAP_MS, 'breath_seconds' => self::BREATH_SECONDS],
        );
    }

    private function option(string $label): array
    {
        return ['id' => 'cbt_ready_' . self::ID, 'label' => $label];
    }

    private function t(string $lang): array
    {
        return self::TEXT[$lang] ?? self::TEXT['en'];
    }
}
