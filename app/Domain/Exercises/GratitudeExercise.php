<?php

namespace App\Domain\Exercises;

use App\Models\GratitudeEntry;
use Carbon\Carbon;

/**
 * Gratitude Journal: three good things from today, saved and shown back later
 * ("Here's what you were grateful for last week").
 *
 * The flow, the saving and the look-back are deterministic. With EXERCISE_AI_ENABLED each answer
 * also gets a short reaction to what the user wrote, and the ending connects today's good things
 * to the earlier entry; otherwise (or on any AI failure) fixed lines are used.
 */
class GratitudeExercise implements Exercise, ProducesRecord
{
    public const ID = 'gratitude_01';
    private const NAME = 'Gratitude Journal';
    private const TOTAL = 3;
    private const MIN_ANSWER_LENGTH = 2;

    private const TEXT = [
        'en' => [
            'prompts' => [
                "Let's take a moment for gratitude. What is one good thing that happened today? Big or small, it all counts.",
                "Lovely. What is a second good thing from today?",
                "And one more. What is a third thing you're grateful for today?",
            ],
            'reactions' => ["That's lovely.", "What a nice thing to notice.", "I'm glad that was part of your day.", "That's worth remembering."],
            'retry' => "Take your time. A few words is enough.",
            'saved_first' => "I've saved these three good things, and I'll show them back to you later.",
            'saved_recall' => "I've saved these three good things. Here's what you were grateful for %s:",
            'thanks' => "Thank you for sharing these. Noticing the good takes practice, and you just did it.",
            'ask' => "How are you feeling now?",
            'when_week' => 'last week',
            'when_yesterday' => 'yesterday',
            'when_days' => '%d days ago',
        ],
        'hi' => [
            'prompts' => [
                "Chaliye thoda shukr-guzaar hote hain. Aaj ki ek achhi baat kya thi? Chhoti ho ya badi, sab chalega.",
                "Bahut accha. Aaj ki doosri achhi baat kya thi?",
                "Aur ek aur. Teesri cheez kya hai jiske liye aap aaj aabhari hain?",
            ],
            'reactions' => ["Yeh bahut accha hai.", "Yeh dhyan dena achha laga.", "Mujhe khushi hai ki yeh aapke din ka hissa tha.", "Yeh yaad rakhne layak hai."],
            'retry' => "Aaram se. Kuch shabd kaafi hain.",
            'saved_first' => "Maine yeh teeno achhi baatein save kar li hain, aur baad mein aapko dikhaunga.",
            'saved_recall' => "Maine yeh teeno achhi baatein save kar li hain. Yeh dekhiye, aap %s in cheezon ke liye aabhari the:",
            'thanks' => "Yeh sab batane ke liye shukriya. Achhai ko dekhna practice se aata hai, aur aapne abhi yeh kiya.",
            'ask' => "Ab aap kaisa mehsoos kar rahe hain?",
            'when_week' => 'pichhle hafte',
            'when_yesterday' => 'kal',
            'when_days' => '%d din pehle',
        ],
    ];

    public function __construct(protected ExerciseVoice $voice)
    {
    }

    public function id(): string
    {
        return self::ID;
    }

    public function saveRecord(int $userId, int $runId, array $record): void
    {
        GratitudeEntry::create($record + ['user_id' => $userId, 'exercise_run_id' => $runId]);
    }

    public function start(string $lang, array $context = []): array
    {
        $t = $this->t($lang);
        return [new ExerciseStep($t['prompts'][0], 'text_input', [], false, 0, self::TOTAL), ['items' => []]];
    }

    public function advance(array $state, string $inputKind, string $text, string $lang, array $context = []): array
    {
        $t = $this->t($lang);
        $items = $state['items'] ?? [];
        $done = count($items);
        $text = trim($text);

        // Tapped something or sent nothing useful: ask the same question again.
        if ($inputKind !== self::INPUT_TEXT || mb_strlen($text) < self::MIN_ANSWER_LENGTH) {
            return [new ExerciseStep($t['retry'] . "\n\n" . $t['prompts'][$done], 'text_input', [], false, $done, self::TOTAL), $state];
        }

        $items[] = mb_substr($text, 0, 300);
        $state['items'] = $items;

        if (count($items) < self::TOTAL) {
            $reaction = $this->voice->line('gratitude_react', self::NAME,
                'They said they are grateful for: "' . mb_substr($text, 0, 300) . '"', $lang)
                ?? $t['reactions'][$done % count($t['reactions'])];

            return [new ExerciseStep($reaction . "\n\n" . $t['prompts'][count($items)], 'text_input', [], false, count($items), self::TOTAL), $state];
        }

        return [new ExerciseStep($this->ending($items, $lang, $context), 'emoji_slider', [], true, self::TOTAL, self::TOTAL), $state];
    }

    /** Saved when the session completes (see ExerciseSessionService::saveRecord). */
    public function toRecord(array $state): ?array
    {
        $items = $state['items'] ?? [];

        return count($items) === self::TOTAL
            ? ['entry_date' => now()->toDateString(), 'items' => array_values($items)]
            : null;
    }

    // ---------------------------------------------------------------- the look-back

    private function ending(array $today, string $lang, array $context): string
    {
        $t = $this->t($lang);
        $past = isset($context['user_id']) ? $this->lookBack((int) $context['user_id']) : null;

        $aiContext = 'Today they are grateful for: ' . implode('; ', $today) . '.'
            . ($past
                ? ' Earlier (' . $past['when_en'] . ') they were grateful for: ' . implode('; ', $past['items']) . '.'
                : ' This is their first entry.');
        $close = $this->voice->line('gratitude_close', self::NAME, $aiContext, $lang) ?? $t['thanks'];

        if ($past) {
            $when = $this->whenLabel($past['days'], $t);
            $recall = sprintf($t['saved_recall'], $when) . "\n" . implode("\n", array_map(fn($i) => "• $i", $past['items']));
        } else {
            $recall = $t['saved_first'];
        }

        return $close . "\n\n" . $recall . "\n\n" . $t['ask'];
    }

    /**
     * The earlier entry to show back: the one closest to a week ago (5-9 days), otherwise the
     * most recent one before today. Null when the user has no earlier entry.
     *
     * @return array{items: array, days: int, when_en: string}|null
     */
    private function lookBack(int $userId): ?array
    {
        $entries = GratitudeEntry::where('user_id', $userId)
            ->whereDate('entry_date', '<', now()->toDateString())
            ->orderByDesc('entry_date')
            ->limit(30)
            ->get();

        if ($entries->isEmpty()) {
            return null;
        }

        $daysAgo = fn(GratitudeEntry $e) => (int) Carbon::parse($e->entry_date)->startOfDay()->diffInDays(now()->startOfDay());

        $weekAgo = $entries->filter(fn($e) => $daysAgo($e) >= 5 && $daysAgo($e) <= 9)
            ->sortBy(fn($e) => abs($daysAgo($e) - 7))
            ->first();
        $pick = $weekAgo ?? $entries->first();
        $days = $daysAgo($pick);

        return [
            'items' => array_values((array) $pick->items),
            'days' => $days,
            'when_en' => $days >= 5 && $days <= 9 ? 'last week' : ($days <= 1 ? 'yesterday' : "$days days ago"),
        ];
    }

    private function whenLabel(int $days, array $t): string
    {
        if ($days >= 5 && $days <= 9) {
            return $t['when_week'];
        }
        return $days <= 1 ? $t['when_yesterday'] : sprintf($t['when_days'], $days);
    }

    private function t(string $lang): array
    {
        return self::TEXT[$lang] ?? self::TEXT['en'];
    }
}
