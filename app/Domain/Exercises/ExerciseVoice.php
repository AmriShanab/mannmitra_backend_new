<?php

namespace App\Domain\Exercises;

use App\Ai\AiClient;
use App\Ai\PromptRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The optional "AI voice" of an exercise. Exercises stay deterministic state machines (steps,
 * counting, completion); this only adds a short, personalised line of wording on top.
 *
 * Switched by EXERCISE_AI_ENABLED. When off, or when the AI fails, every method returns null and
 * the exercise uses its own fixed text, so a session never depends on OpenAI to work.
 * After a failure the voice stays quiet for a minute so a broken key or billing problem does not
 * slow every step with a failing call.
 */
class ExerciseVoice
{
    private const BREAKER_KEY = 'exercise_ai_down';
    private const MAX_LINE_CHARS = 240;

    // kind => [task, length]. The task is given to the model; the length caps verbosity.
    private const TASKS = [
        'open' => ['Warmly welcome them to this calming exercise, gently reflecting how they said they feel today if that is known.', 'one short sentence (max 25 words)'],
        'close' => ['Praise them for taking time for themselves and name the calm they may feel now.', 'one or two short sentences (max 35 words)'],
        'interrupt' => ['They typed a message in the middle of a calming exercise. Respond to what they actually said with care, and reassure them they can carry on.', 'one or two short sentences (max 35 words)'],
        'grounding_react' => ['React warmly to the specific things they just named, like a friend noticing a detail.', 'one short sentence (max 20 words)'],
        'grounding_close' => ['Close the grounding exercise by warmly acknowledging what they noticed across their senses.', 'one or two short sentences (max 40 words)'],
        'gratitude_react' => ['React warmly to the specific good thing they just named, like a friend who is glad for them.', 'one short sentence (max 20 words)'],
        'gratitude_close' => ['Warmly point out how much good they noticed today. If an earlier entry is given, gently connect today to it without listing it again. Be sincere, not over the top.', 'one or two short sentences (max 40 words)'],
        'sleep_react' => ['React with gentle empathy to how they said they slept last night, and say you will help them wind down tonight.', 'one short sentence (max 22 words)'],
        'sleep_close' => ['Wish them a restful night warmly and calmly. If they set up helpful habits, appreciate that without lecturing.', 'one or two short sentences (max 30 words)'],
        'goal_react' => ['Acknowledge, with warmth, the core values they chose and what they say about what matters to them.', 'one short sentence (max 22 words)'],
        'goal_close' => ['Encourage them warmly about the small goal they set for the week, tying it to their values. If they made it smaller, praise that as wise. Do not add new tasks.', 'one or two short sentences (max 35 words)'],
        'reframing_situation' => ['Acknowledge the situation they described with empathy.', 'one short sentence (max 20 words)'],
        'reframing_evidence' => ['Acknowledge the evidence they found against the upsetting thought, and say that it counts.', 'one short sentence (max 25 words)'],
        'reframing_balanced' => ['Appreciate the fairer thought they wrote and how it differs from the original upsetting thought.', 'one short sentence (max 25 words)'],
        'reframing_close' => ['Close warmly based on how their distress changed (before versus after, out of 10). Be honest and gentle if it did not shift.', 'one or two short sentences (max 40 words)'],
    ];

    public function __construct(protected AiClient $client, protected PromptRepository $prompts)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('openai.exercise_ai.enabled') && !Cache::has(self::BREAKER_KEY);
    }

    /**
     * One personalised line, or null (disabled, failed, or rejected by the safety checks).
     *
     * @param string $kind a key of TASKS
     * @param string $context what the person said / how they are, in plain words
     */
    public function line(string $kind, string $exercise, string $context, string $lang): ?string
    {
        if (!$this->enabled() || !isset(self::TASKS[$kind])) {
            return null;
        }

        [$task, $length] = self::TASKS[$kind];
        $system = $this->prompts->render('exercise_voice', [
            'exercise' => $exercise,
            'task' => $task,
            'length' => $length,
            'context' => mb_substr($context, 0, 900),
            'language' => PromptRepository::languageName($lang),
        ]);

        $data = $this->json("exercise.voice.$kind", $system, 'Write the line now.');
        return $this->clean($data['line'] ?? null);
    }

    /**
     * Low-level JSON call on the mini model for exercise-specific prompts. Null when off or failed.
     */
    public function json(string $feature, string $system, string $user, int $maxTokens = 90): ?array
    {
        if (!$this->enabled()) {
            return null;
        }

        try {
            return $this->client->chatJson(
                $feature, $system, $user, AiClient::TIER_MINI, 0.7, $maxTokens,
                (int) config('openai.exercise_ai.timeout', 8)
            );
        } catch (\Throwable $e) {
            Log::warning("Exercise AI call failed ($feature); using fixed text for the next minute: " . $e->getMessage());
            Cache::put(self::BREAKER_KEY, true, (int) config('openai.exercise_ai.breaker_seconds', 60));
            return null;
        }
    }

    /** Puts the optional AI line in front of the exercise's own text. */
    public static function compose(?string $line, string $then): string
    {
        return $line ? $line . "\n\n" . $then : $then;
    }

    /** "They rated their mood today 3/10" style context shared by several exercises. */
    public static function moodContext(?int $mood): string
    {
        return $mood === null
            ? 'Their mood today is not known.'
            : "They rated their mood today {$mood}/10 (1 is very low, 10 is great).";
    }

    private function clean(?string $line): ?string
    {
        if ($line === null) {
            return null;
        }

        $line = trim(preg_replace('/\s+/', ' ', $line));

        // The app asks the questions; a line with its own question would collide with them.
        if ($line === '' || mb_strlen($line) > self::MAX_LINE_CHARS || str_contains($line, '?')) {
            return null;
        }

        return $line;
    }
}
