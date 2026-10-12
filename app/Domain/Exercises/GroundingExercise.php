<?php

namespace App\Domain\Exercises;

use App\Ai\PromptRepository;
use App\Services\AIService;
use Illuminate\Support\Facades\Log;

/**
 * 5-4-3-2-1 grounding as a fixed sequence. The order is enforced here; the AI only adds an
 * optional one-line reaction per step (mini model) and a canned line replaces it on any failure.
 */
class GroundingExercise implements Exercise
{
    public const ID = 'grounding_01';
    private const MIN_ANSWER_LENGTH = 2;

    // [count, verb used in the reaction prompt]
    private const SENSES = ['see', 'feel', 'hear', 'smell', 'taste'];
    private const COUNTS = [5, 4, 3, 2, 1];

    private const TEXT = [
        'en' => [
            'prompts' => [
                "Let's do a quick grounding exercise together. Look around you and type 5 things you can SEE right now.",
                "Now notice 4 things you can FEEL, like your feet on the floor or the fabric of your clothes.",
                "Listen closely. What are 3 things you can HEAR?",
                "Take a gentle breath in. What are 2 things you can SMELL?",
                "Last one. What is 1 thing you can TASTE right now?",
            ],
            'reactions' => ["Thank you for sharing that.", "That's a good one.", "Nice, you're doing great.", "I like that."],
            'retry' => "Take your time. Just type what you notice, a word or two is fine.",
            'done' => "I'm so proud of you for doing this. How are you feeling right now?",
        ],
        'hi' => [
            'prompts' => [
                "Chaliye grounding exercise karte hain. Apne aas-paas dekhiye aur 5 cheezein bataiye jo aap abhi DEKH sakte hain.",
                "Ab 4 cheezein bataiye jo aap MEHSOOS kar sakte hain, jaise zameen par pair ya kapde ka sparsh.",
                "Dhyan se suniye. 3 cheezein bataiye jo aap SUN sakte hain.",
                "Ek halki si saans lijiye. 2 cheezein bataiye jinki aap KHUSHBOO le sakte hain.",
                "Aakhri wali. 1 cheez bataiye jiska aap abhi SWAAD le sakte hain.",
            ],
            'reactions' => ["Batane ke liye shukriya.", "Yeh accha hai.", "Bahut badhiya, aap accha kar rahe hain.", "Mujhe yeh pasand aaya."],
            'retry' => "Aaram se. Jo mehsoos ho bas likh dijiye, ek-do shabd bhi chalenge.",
            'done' => "Mujhe aap par bahut garv hai. Ab aap kaisa mehsoos kar rahe hain?",
        ],
    ];

    public function __construct(protected AIService $ai, protected PromptRepository $prompts)
    {
    }

    public function id(): string
    {
        return self::ID;
    }

    public function start(string $lang): array
    {
        $t = self::TEXT[$lang] ?? self::TEXT['en'];
        $state = ['sense' => 0];

        return [new ExerciseStep($t['prompts'][0], 'text_input', [], false, 0, count(self::SENSES)), $state];
    }

    public function advance(array $state, string $inputKind, string $text, string $lang): array
    {
        $t = self::TEXT[$lang] ?? self::TEXT['en'];
        $sense = (int) ($state['sense'] ?? 0);
        $total = count(self::SENSES);
        $text = trim($text);

        // Tapped a button or sent nothing useful: ask the same question again.
        if ($inputKind !== self::INPUT_TEXT || mb_strlen($text) < self::MIN_ANSWER_LENGTH) {
            return [new ExerciseStep($t['retry'] . "\n\n" . $t['prompts'][$sense], 'text_input', [], false, $sense, $total), $state];
        }

        $next = $sense + 1;
        $state['sense'] = $next;

        if ($next >= $total) {
            return [new ExerciseStep($t['done'], 'emoji_slider', [], true, $total, $total), $state];
        }

        $reaction = $this->react(self::SENSES[$sense], $text, $lang, $t['reactions'], $sense);

        return [
            new ExerciseStep($reaction . "\n\n" . $t['prompts'][$next], 'text_input', [], false, $next, $total, ['count' => self::COUNTS[$next]]),
            $state,
        ];
    }

    private function react(string $sense, string $answer, string $lang, array $canned, int $index): string
    {
        try {
            $system = $this->prompts->render('grounding_reaction', [
                'sense_verb' => $sense,
                'language' => PromptRepository::languageName($lang),
            ]);
            $data = $this->ai->getMiniCompletion($system, 'User said: "' . mb_substr($answer, 0, 300) . '"', 'exercise.grounding.reaction', 60);
            $reaction = trim((string) ($data['reaction'] ?? ''));

            if ($reaction !== '' && mb_strlen($reaction) <= 160) {
                return $reaction;
            }
        } catch (\Throwable $e) {
            Log::warning('Grounding reaction failed, using canned line: ' . $e->getMessage());
        }

        return $canned[$index % count($canned)];
    }
}
