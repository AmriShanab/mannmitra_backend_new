<?php

namespace App\Domain\Exercises;

use App\Ai\PromptRepository;

/**
 * Cognitive reframing as a structured thought record:
 * situation -> thought -> (pattern + empathy) -> distress -> evidence against -> balanced thought -> distress again.
 *
 * The flow and the saved record are deterministic. With EXERCISE_AI_ENABLED the app also names the
 * thinking pattern and answers each step with a short line that reflects what the user wrote;
 * otherwise (or on any AI failure) fixed lines are used.
 */
class ReframingExercise implements Exercise, ProducesRecord
{
    public const ID = 'reframing_01';
    private const NAME = 'Reframing';

    private const STEP_SITUATION = 0;
    private const STEP_THOUGHT = 1;
    private const STEP_INTENSITY_BEFORE = 2;
    private const STEP_EVIDENCE = 3;
    private const STEP_BALANCED = 4;
    private const STEP_INTENSITY_AFTER = 5;
    private const TOTAL = 6;

    private const DISTORTIONS = [
        'all_or_nothing', 'catastrophizing', 'mind_reading', 'overgeneralization',
        'emotional_reasoning', 'should_statements', 'labeling', 'personalization',
    ];

    // Button id => 1-10 distress score.
    private const LEVELS = ['rf_int_3' => 3, 'rf_int_6' => 6, 'rf_int_9' => 9];
    private const MIN_ANSWER_LENGTH = 3;

    private const TEXT = [
        'en' => [
            'situation' => "Let's work through this together. What happened? Tell me about the situation in a sentence or two.",
            'thought' => "Thank you for telling me. What thought went through your mind in that moment?",
            'reflection' => "That sounds really hard to carry.",
            'intensity' => "How strongly does that thought affect you right now?",
            'evidence' => "Let's look at it from another angle. What evidence suggests this thought might not be completely true? Even small things count.",
            'balanced' => "That's helpful. Now, how could you say the same thing in a fairer, more balanced way?",
            'intensity_after' => "Now that you've reframed it, how strongly does the original thought affect you?",
            'retry' => "Take your time. A sentence or two is plenty.",
            'pick' => "Please tap one of the options below.",
            'levels' => ['rf_int_3' => 'Mild', 'rf_int_6' => 'Strong', 'rf_int_9' => 'Very strong'],
            'better' => "That's a real shift, and you did that. Your new thought is worth remembering: \"%s\". How are you feeling now?",
            'same' => "It's okay if it hasn't shifted much yet; reframing takes practice, and you showed up for it. Your balanced thought: \"%s\". How are you feeling now?",
            'tail' => "Your balanced thought: \"%s\". How are you feeling now?",
        ],
        'hi' => [
            'situation' => "Chaliye isse saath mein samajhte hain. Kya hua? Ek-do line mein situation bataiye.",
            'thought' => "Batane ke liye shukriya. Us waqt aapke dimaag mein kaun sa vichaar aaya?",
            'reflection' => "Yeh sach mein bhaari lagta hoga.",
            'intensity' => "Yeh vichaar abhi aap par kitna asar kar raha hai?",
            'evidence' => "Ab ise doosre nazariye se dekhte hain. Kaun si baatein batati hain ki yeh vichaar poori tarah sach nahi hai? Chhoti baatein bhi chalengi.",
            'balanced' => "Yeh madadgaar hai. Ab wahi baat thodi zyada santulit tareeke se kaise keh sakte hain?",
            'intensity_after' => "Ab jab aapne ise naye tareeke se dekha, to purana vichaar aap par kitna asar kar raha hai?",
            'retry' => "Aaram se. Ek-do line kaafi hain.",
            'pick' => "Kripya neeche diye gaye vikalpon mein se ek chuniye.",
            'levels' => ['rf_int_3' => 'Halka', 'rf_int_6' => 'Tez', 'rf_int_9' => 'Bahut tez'],
            'better' => "Yeh sach mein ek badlaav hai, aur yeh aapne kiya. Aapka naya vichaar: \"%s\". Ab aap kaisa mehsoos kar rahe hain?",
            'same' => "Koi baat nahi agar abhi zyada fark nahi pada; reframing practice se aati hai, aur aap aaye. Aapka santulit vichaar: \"%s\". Ab aap kaisa mehsoos kar rahe hain?",
            'tail' => "Aapka santulit vichaar: \"%s\". Ab aap kaisa mehsoos kar rahe hain?",
        ],
    ];

    public function __construct(protected ExerciseVoice $voice, protected PromptRepository $prompts)
    {
    }

    public function id(): string
    {
        return self::ID;
    }

    public function start(string $lang, array $context = []): array
    {
        $t = $this->t($lang);
        return [new ExerciseStep($t['situation'], 'text_input', [], false, 0, self::TOTAL), ['step' => self::STEP_SITUATION]];
    }

    public function advance(array $state, string $inputKind, string $text, string $lang): array
    {
        $t = $this->t($lang);
        $step = (int) ($state['step'] ?? self::STEP_SITUATION);
        $text = trim($text);

        // The message for the next step is built lazily so a rejected answer never costs an AI call.
        return match ($step) {
            self::STEP_SITUATION => $this->takeText($state, $inputKind, $text, 'situation', self::STEP_THOUGHT,
                fn() => ExerciseVoice::compose(
                    $this->voice->line('reframing_situation', self::NAME, 'They described this situation: "' . $this->cut($text) . '"', $lang),
                    $t['thought']), $lang),
            self::STEP_THOUGHT => $this->takeThought($state, $inputKind, $text, $lang),
            self::STEP_INTENSITY_BEFORE => $this->takeLevel($state, $inputKind, $text, 'intensity_before', self::STEP_EVIDENCE, $t['evidence'], 'text_input', $lang),
            self::STEP_EVIDENCE => $this->takeText($state, $inputKind, $text, 'evidence_against', self::STEP_BALANCED,
                fn() => ExerciseVoice::compose(
                    $this->voice->line('reframing_evidence', self::NAME,
                        'Their upsetting thought: "' . $this->cut($state['automatic_thought'] ?? '') . '". Evidence they found against it: "' . $this->cut($text) . '"', $lang),
                    $t['balanced']), $lang),
            self::STEP_BALANCED => $this->takeText($state, $inputKind, $text, 'balanced_thought', self::STEP_INTENSITY_AFTER,
                fn() => ExerciseVoice::compose(
                    $this->voice->line('reframing_balanced', self::NAME,
                        'Their original thought: "' . $this->cut($state['automatic_thought'] ?? '') . '". Their fairer thought: "' . $this->cut($text) . '"', $lang),
                    $t['intensity_after']), $lang, $this->levelOptions($t)),
            default => $this->finish($state, $inputKind, $text, $lang),
        };
    }

    public function toRecord(array $state): ?array
    {
        foreach (['situation', 'automatic_thought', 'intensity_before', 'evidence_against', 'balanced_thought', 'intensity_after'] as $key) {
            if (!isset($state[$key])) {
                return null;
            }
        }

        return [
            'situation' => $state['situation'],
            'automatic_thought' => $state['automatic_thought'],
            'distortion' => $state['distortion'] ?? null,
            'intensity_before' => $state['intensity_before'],
            'evidence_against' => $state['evidence_against'],
            'balanced_thought' => $state['balanced_thought'],
            'intensity_after' => $state['intensity_after'],
        ];
    }

    // ------------------------------------------------------------------ steps

    /** @param string|\Closure $nextMessage text, or a closure that builds it once the answer is accepted */
    private function takeText(array $state, string $kind, string $text, string $field, int $nextStep, string|\Closure $nextMessage, string $lang, array $nextOptions = []): array
    {
        $t = $this->t($lang);
        $step = (int) $state['step'];

        if ($kind !== self::INPUT_TEXT || mb_strlen($text) < self::MIN_ANSWER_LENGTH) {
            return [new ExerciseStep($t['retry'], 'text_input', [], false, $step, self::TOTAL), $state];
        }

        $state[$field] = mb_substr($text, 0, 1000);
        $state['step'] = $nextStep;
        $message = $nextMessage instanceof \Closure ? $nextMessage() : $nextMessage;

        return [new ExerciseStep($message, $nextOptions ? 'buttons' : 'text_input', $nextOptions, false, $nextStep, self::TOTAL), $state];
    }

    private function takeThought(array $state, string $kind, string $text, string $lang): array
    {
        $t = $this->t($lang);

        if ($kind !== self::INPUT_TEXT || mb_strlen($text) < self::MIN_ANSWER_LENGTH) {
            return [new ExerciseStep($t['retry'], 'text_input', [], false, self::STEP_THOUGHT, self::TOTAL), $state];
        }

        $state['automatic_thought'] = mb_substr($text, 0, 1000);
        $state['step'] = self::STEP_INTENSITY_BEFORE;
        [$state['distortion'], $reflection] = $this->analyse($text, $lang, $t['reflection']);

        return [
            new ExerciseStep($reflection . "\n\n" . $t['intensity'], 'buttons', $this->levelOptions($t), false, self::STEP_INTENSITY_BEFORE, self::TOTAL),
            $state,
        ];
    }

    private function takeLevel(array $state, string $kind, string $text, string $field, int $nextStep, string $nextMessage, string $nextMode, string $lang): array
    {
        $t = $this->t($lang);
        $step = (int) $state['step'];
        $score = $this->score($kind, $text);

        if ($score === null) {
            return [new ExerciseStep($t['pick'], 'buttons', $this->levelOptions($t), false, $step, self::TOTAL), $state];
        }

        $state[$field] = $score;
        $state['step'] = $nextStep;

        return [new ExerciseStep($nextMessage, $nextMode, [], false, $nextStep, self::TOTAL), $state];
    }

    private function finish(array $state, string $kind, string $text, string $lang): array
    {
        $t = $this->t($lang);
        $score = $this->score($kind, $text);

        if ($score === null) {
            return [new ExerciseStep($t['pick'], 'buttons', $this->levelOptions($t), false, self::STEP_INTENSITY_AFTER, self::TOTAL), $state];
        }

        $state['intensity_after'] = $score;
        $state['step'] = self::TOTAL;
        $before = (int) ($state['intensity_before'] ?? $score);
        $balanced = $state['balanced_thought'] ?? '';

        $close = $this->voice->line('reframing_close', self::NAME,
            "Their upsetting thought: \"" . $this->cut($state['automatic_thought'] ?? '') . "\". Distress before: {$before}/10, after: {$score}/10. Their balanced thought: \"" . $this->cut($balanced) . '"', $lang);

        $message = $close
            ? $close . "\n\n" . sprintf($t['tail'], $balanced)
            : sprintf($score < $before ? $t['better'] : $t['same'], $balanced);

        return [new ExerciseStep($message, 'emoji_slider', [], true, self::TOTAL, self::TOTAL), $state];
    }

    // ---------------------------------------------------------------- helpers

    private function score(string $kind, string $text): ?int
    {
        if ($kind === self::INPUT_CHOICE) {
            return self::LEVELS[$text] ?? null;
        }
        // Typed "1".."10" is fine too.
        if ($kind === self::INPUT_TEXT && ctype_digit($text) && (int) $text >= 1 && (int) $text <= 10) {
            return (int) $text;
        }
        return null;
    }

    private function levelOptions(array $t): array
    {
        $options = [];
        foreach ($t['levels'] as $id => $label) {
            $options[] = ['id' => $id, 'label' => $label];
        }
        return $options;
    }

    private function cut(string $text): string
    {
        return mb_substr($text, 0, 300);
    }

    /** @return array{0: ?string, 1: string} [distortion, reflection] */
    private function analyse(string $thought, string $lang, string $fallbackReflection): array
    {
        if (!$this->voice->enabled()) {
            return [null, $fallbackReflection];
        }

        $system = $this->prompts->render('distortion', ['language' => PromptRepository::languageName($lang)]);
        $data = $this->voice->json('exercise.reframing.analyse', $system, 'Thought: "' . $this->cut($thought) . '"');

        $distortion = in_array($data['distortion'] ?? null, self::DISTORTIONS, true) ? $data['distortion'] : null;
        $reflection = trim((string) ($data['reflection'] ?? ''));

        return [$distortion, ($reflection !== '' && mb_strlen($reflection) <= 200) ? $reflection : $fallbackReflection];
    }

    private function t(string $lang): array
    {
        return self::TEXT[$lang] ?? self::TEXT['en'];
    }
}
