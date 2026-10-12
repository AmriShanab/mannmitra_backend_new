<?php

namespace App\Domain\Exercises;

use App\Services\GoalService;

/**
 * Values & Goal Setting: the user picks up to three core values (family, health, ...), picks one to
 * focus on, and turns it into one small weekly goal. The next time they open the exercise the bot
 * first checks in on that goal ("did you do it?"), then offers a fresh one.
 *
 * Phases: checkin -> ci_next -> values -> focus -> goal -> own -> confidence -> shrink -> done.
 * The flow, the saving and the check-in are deterministic. With EXERCISE_AI_ENABLED the bot adds a
 * short personal line after the values and at the end; otherwise (or on any AI failure) fixed
 * lines are used.
 */
class ValuesGoalExercise implements Exercise, ProducesRecord
{
    public const ID = 'values_goal_01';
    private const NAME = 'Values & Goal';
    private const TOTAL = 4;
    private const MAX_VALUES = 3;
    private const MIN_GOAL_LENGTH = 3;

    private const VALUE_IDS = ['family', 'health', 'work', 'friends', 'growth', 'creativity', 'calm', 'faith', 'fun'];

    private const LABELS = [
        'en' => ['family' => 'Family', 'health' => 'Health', 'work' => 'Work or career', 'friends' => 'Friends', 'growth' => 'Learning and growth',
            'creativity' => 'Creativity', 'calm' => 'Inner peace', 'faith' => 'Faith', 'fun' => 'Fun and play'],
        'hi' => ['family' => 'Parivaar', 'health' => 'Sehat', 'work' => 'Kaam ya career', 'friends' => 'Dost', 'growth' => 'Seekhna aur aage badhna',
            'creativity' => 'Creativity', 'calm' => 'Mann ki shaanti', 'faith' => 'Aastha', 'fun' => 'Masti aur khel'],
    ];

    // Three small, concrete goals for each value. The user can always write their own.
    private const GOALS = [
        'en' => [
            'family' => ["Call or message a family member I haven't spoken to lately", "Have one meal with family without phones", "Do one kind thing for someone at home"],
            'health' => ["Walk for 15 minutes on 3 days", "Drink enough water on most days", "Go to bed at a fixed time on 4 nights"],
            'work' => ["Spend 25 focused minutes on my top task each day", "Plan tomorrow's top 3 tasks each evening", "Take one real break away from my desk each afternoon"],
            'friends' => ["Reach out to one friend this week", "Make a plan to meet a friend", "Send one kind message to a friend"],
            'growth' => ["Learn something new for 15 minutes on 4 days", "Read 10 pages on 4 days", "Try one new thing this week"],
            'creativity' => ["Spend 15 minutes on something creative on 3 days", "Write or doodle for 10 minutes on 4 days", "Start one small creative project"],
            'calm' => ["Do a 5-minute breathing exercise on most days", "Spend 10 quiet minutes without my phone each day", "Write down my thoughts for 5 minutes each evening"],
            'faith' => ["Take 10 quiet minutes for prayer or reflection each day", "Visit a place that feels meaningful to me", "Read or listen to something uplifting each day"],
            'fun' => ["Do one fun thing I enjoy this week", "Spend 30 minutes on a hobby on 3 days", "Plan one outing for the weekend"],
        ],
        'hi' => [
            'family' => ["Parivaar ke kisi sadasya ko call ya message karna jisse kaafi time se baat nahi hui", "Ek baar parivaar ke saath bina phone ke khana khana", "Ghar mein kisi ke liye ek achha kaam karna"],
            'health' => ["3 din 15 minute tehalna", "Zyaadatar din kaafi paani peena", "4 raat tay samay par sona"],
            'work' => ["Roz 25 minute dhyaan lagakar sabse zaroori kaam par kaam karna", "Roz shaam ko kal ke 3 zaroori kaam likhna", "Roz dopahar mein desk se door ek asli break lena"],
            'friends' => ["Is hafte ek dost se baat karna", "Kisi dost se milne ka plan banana", "Ek dost ko pyara sa message bhejna"],
            'growth' => ["4 din 15 minute kuch naya seekhna", "4 din 10 page padhna", "Is hafte ek nayi cheez try karna"],
            'creativity' => ["3 din 15 minute kuch creative karna", "4 din 10 minute likhna ya doodle banana", "Ek chhota creative project shuru karna"],
            'calm' => ["Zyaadatar din 5 minute breathing exercise karna", "Roz 10 minute bina phone ke shaanti se baithna", "Roz shaam 5 minute apne vichar likhna"],
            'faith' => ["Roz 10 minute prarthna ya chintan ke liye nikalna", "Ek aisi jagah jaana jo mere liye khaas ho", "Roz kuch prerak padhna ya sunna"],
            'fun' => ["Is hafte ek maze ki cheez karna", "3 din 30 minute apna shauk poora karna", "Weekend ke liye ek outing plan karna"],
        ],
    ];

    private const TEXT = [
        'en' => [
            'ci' => "Welcome back. Last time you set this goal:\n\"%s\"\n\nHow did it go?",
            'ci_opts' => ['goal_ci_done' => 'I did it', 'goal_ci_partly' => 'Partly', 'goal_ci_not' => 'Not yet'],
            'ci_react' => ['done' => "Well done! That's real progress, and it's worth being proud of.", 'partly' => "Partly still counts. Any effort moves you forward.", 'not' => "That's okay. Some weeks are like that, and a new week is a fresh start."],
            'ci_next_done' => "Would you like to set a new goal for this week?",
            'ci_next_open' => "Would you like to keep the same goal for another week, or set a new one?",
            'ci_next_opts_done' => ['goal_new' => 'Set a new goal', 'goal_stop' => 'Not now'],
            'ci_next_opts_open' => ['goal_keep' => 'Keep this goal', 'goal_new' => 'Set a new goal'],
            'values' => "Let's think about what matters most to you right now. Pick up to three.",
            'continue' => 'Continue',
            'pick_values' => "Please choose at least one value to continue.",
            'values_react' => "Those are meaningful choices.",
            'focus' => "Which one would you like to focus on this week?",
            'goal' => "Let's turn \"%s\" into one small step for this week. Pick one, or write your own.",
            'goal_own' => 'Write my own',
            'own' => "Write your goal in one sentence. Keep it small, something you can really do this week.",
            'confidence' => "How sure are you that you can do this?\n\n\"%s\"",
            'conf_opts' => ['goal_conf_3' => 'Very sure', 'goal_conf_2' => 'Fairly sure', 'goal_conf_1' => 'Not very sure'],
            'shrink' => "Let's make it smaller so it feels doable. What is an easier version you're nearly sure you can do?",
            'pick' => "Please choose one of the options.",
            'retry_text' => "A few words are enough. What would you like your goal to be?",
            'saved' => "Your goal for this week:\n\"%s\"\nI'll check in with you on it next week.",
            'kept' => "We'll keep this goal for another week:\n\"%s\"\nI'll check in with you again then.",
            'stopped' => "No problem. Come back whenever you'd like to set a new one.",
            'bye' => "Small steps toward what matters add up. You've started well.",
            'ask' => "How are you feeling right now?",
        ],
        'hi' => [
            'ci' => "Wapas aapka swagat hai. Pichhli baar aapne yeh goal rakha tha:\n\"%s\"\n\nKaisa raha?",
            'ci_opts' => ['goal_ci_done' => 'Maine kar liya', 'goal_ci_partly' => 'Thoda kiya', 'goal_ci_not' => 'Abhi nahi'],
            'ci_react' => ['done' => "Bahut badhiya! Yeh sachmuch progress hai, aur aapko is par garv hona chahiye.", 'partly' => "Thoda karna bhi ginta hai. Har koshish aapko aage le jaati hai.", 'not' => "Koi baat nahi. Kuch hafte aise hi hote hain, aur naya hafta nayi shuruaat hai."],
            'ci_next_done' => "Kya aap is hafte ke liye naya goal rakhna chahenge?",
            'ci_next_open' => "Kya aap yahi goal ek hafta aur rakhna chahenge, ya naya?",
            'ci_next_opts_done' => ['goal_new' => 'Naya goal rakhein', 'goal_stop' => 'Abhi nahi'],
            'ci_next_opts_open' => ['goal_keep' => 'Yahi goal rakhein', 'goal_new' => 'Naya goal rakhein'],
            'values' => "Chaliye sochte hain ki abhi aapke liye sabse zaroori kya hai. Zyaada se zyaada teen chuniye.",
            'continue' => 'Aage badhein',
            'pick_values' => "Aage badhne ke liye kam se kam ek value chuniye.",
            'values_react' => "Yeh bahut achhe chunav hain.",
            'focus' => "Is hafte aap kis par dhyaan dena chahenge?",
            'goal' => "Chaliye \"%s\" ko is hafte ke ek chhote kadam mein badalte hain. Ek chuniye, ya apna khud likhiye.",
            'goal_own' => 'Apna khud likhein',
            'own' => "Apna goal ek vaakya mein likhiye. Chhota rakhiye, jo aap sach mein is hafte kar sakein.",
            'confidence' => "Aapko kitna bharosa hai ki aap yeh kar paayenge?\n\n\"%s\"",
            'conf_opts' => ['goal_conf_3' => 'Bahut bharosa hai', 'goal_conf_2' => 'Kaafi bharosa hai', 'goal_conf_1' => 'Zyaada nahi'],
            'shrink' => "Chaliye ise chhota karte hain taaki yeh aasaan lage. Iska ek aasaan roop kya hoga jo aap lagbhag pakka kar paayenge?",
            'pick' => "Kripya ek vikalp chuniye.",
            'retry_text' => "Kuch shabd kaafi hain. Aapka goal kya hoga?",
            'saved' => "Is hafte ka aapka goal:\n\"%s\"\nMain agle hafte aapse iske baare mein poochhunga.",
            'kept' => "Hum yeh goal ek hafta aur rakhenge:\n\"%s\"\nMain tab phir poochhunga.",
            'stopped' => "Koi baat nahi. Jab bhi naya goal rakhna ho, wapas aa jaiye.",
            'bye' => "Jo zaroori hai uski taraf chhote kadam jud kar bade ho jaate hain. Aapne achhi shuruaat ki hai.",
            'ask' => "Ab aap kaisa mehsoos kar rahe hain?",
        ],
    ];

    public function __construct(protected ExerciseVoice $voice, protected GoalService $goals)
    {
    }

    public function id(): string
    {
        return self::ID;
    }

    public static function valueLabel(string $value, string $lang): string
    {
        return (self::LABELS[$lang] ?? self::LABELS['en'])[$value] ?? $value;
    }

    public function start(string $lang, array $context = []): array
    {
        $t = $this->t($lang);
        $due = isset($context['user_id']) ? $this->goals->dueForCheckIn((int) $context['user_id']) : null;

        if ($due) {
            $state = ['phase' => 'checkin', 'goal_id' => $due->id, 'ci_goal' => $due->goal_text];
            return [$this->stepFor($state, $lang), $state];
        }

        $state = ['phase' => 'values'];
        return [$this->stepFor($state, $lang), $state];
    }

    public function advance(array $state, string $inputKind, string $text, string $lang, array $context = []): array
    {
        $t = $this->t($lang);
        $phase = $state['phase'] ?? 'values';
        $choice = $inputKind === self::INPUT_CHOICE ? trim($text) : '';
        $free = $inputKind === self::INPUT_TEXT ? trim($text) : '';

        return match ($phase) {
            'checkin' => $this->onCheckIn($state, $choice, $lang, $t),
            'ci_next' => $this->onCheckInNext($state, $choice, $lang, $t),
            'values' => $this->onValues($state, $choice, $lang, $t),
            'focus' => $this->onFocus($state, $choice, $lang, $t),
            'goal' => $this->onGoal($state, $choice, $free, $lang, $t),
            'own' => $this->onOwn($state, $free, $lang, $t),
            'confidence' => $this->onConfidence($state, $choice, $lang, $t),
            'shrink' => $this->onShrink($state, $free, $lang, $t),
            default => [$this->doneStep($state, $lang), $state],
        };
    }

    // ------------------------------------------------------------------ phases

    private function onCheckIn(array $state, string $choice, string $lang, array $t): array
    {
        $progress = ['goal_ci_done' => 'done', 'goal_ci_partly' => 'partly', 'goal_ci_not' => 'not'][$choice] ?? null;
        if ($progress === null) {
            return [$this->stepFor($state, $lang, $t['pick']), $state];
        }

        // Saved right away so the check-in is not lost if the user stops here.
        $goal = \App\Models\UserGoal::find($state['goal_id'] ?? 0);
        if ($goal) {
            $this->goals->checkIn($goal, $progress);
        }

        $state['phase'] = 'ci_next';
        $state['ci'] = $progress;

        return [$this->stepFor($state, $lang, $t['ci_react'][$progress]), $state];
    }

    private function onCheckInNext(array $state, string $choice, string $lang, array $t): array
    {
        $allowed = ($state['ci'] ?? 'not') === 'done' ? ['goal_new', 'goal_stop'] : ['goal_keep', 'goal_new'];

        if (!in_array($choice, $allowed, true)) {
            return [$this->stepFor($state, $lang, $t['pick']), $state];
        }

        if ($choice === 'goal_new') {
            $state['phase'] = 'values';
            return [$this->stepFor($state, $lang), $state];
        }

        if ($choice === 'goal_keep') {
            $goal = \App\Models\UserGoal::find($state['goal_id'] ?? 0);
            if ($goal) {
                $this->goals->keep($goal);
            }
            $state['kept'] = true;
        }

        $state['phase'] = 'done';
        return [$this->doneStep($state, $lang), $state];
    }

    private function onValues(array $state, string $choice, string $lang, array $t): array
    {
        if (!str_starts_with($choice, 'goal_vals:')) {
            return [$this->stepFor($state, $lang, $t['pick']), $state];
        }

        $ids = array_filter(explode(',', substr($choice, strlen('goal_vals:'))));
        $values = array_slice(array_values(array_intersect(self::VALUE_IDS, $ids)), 0, self::MAX_VALUES); // known ids only

        if ($values === []) {
            return [$this->stepFor($state, $lang, $t['pick_values']), $state];
        }

        $state['values'] = $values;
        $labels = array_map(fn($v) => self::valueLabel($v, $lang), $values);
        $react = $this->voice->line('goal_react', self::NAME, 'They chose these as their core values: ' . implode(', ', $labels) . '.', $lang) ?? $t['values_react'];

        if (count($values) === 1) {
            $state['focus'] = $values[0];
            $state['phase'] = 'goal';
        } else {
            $state['phase'] = 'focus';
        }

        return [$this->stepFor($state, $lang, $react), $state];
    }

    private function onFocus(array $state, string $choice, string $lang, array $t): array
    {
        $focus = str_starts_with($choice, 'goal_focus_') ? substr($choice, strlen('goal_focus_')) : '';

        if (!in_array($focus, $state['values'] ?? [], true)) {
            return [$this->stepFor($state, $lang, $t['pick']), $state];
        }

        $state['focus'] = $focus;
        $state['phase'] = 'goal';

        return [$this->stepFor($state, $lang), $state];
    }

    private function onGoal(array $state, string $choice, string $free, string $lang, array $t): array
    {
        $suggestions = $this->suggestions($state['focus'] ?? '', $lang);

        // Typing a goal straight away is fine too.
        if ($free !== '' && mb_strlen($free) >= self::MIN_GOAL_LENGTH) {
            return $this->withGoal($state, mb_substr($free, 0, 140), $lang);
        }

        if ($choice === 'goal_own') {
            $state['phase'] = 'own';
            return [$this->stepFor($state, $lang), $state];
        }

        if (preg_match('/^goal_pick_([1-3])$/', $choice, $m) && isset($suggestions[(int) $m[1] - 1])) {
            return $this->withGoal($state, $suggestions[(int) $m[1] - 1], $lang);
        }

        return [$this->stepFor($state, $lang, $t['pick']), $state];
    }

    private function onOwn(array $state, string $free, string $lang, array $t): array
    {
        if (mb_strlen($free) < self::MIN_GOAL_LENGTH) {
            return [$this->stepFor($state, $lang, $t['retry_text']), $state];
        }

        return $this->withGoal($state, mb_substr($free, 0, 140), $lang);
    }

    private function withGoal(array $state, string $goal, string $lang): array
    {
        $state['goal'] = $goal;
        $state['phase'] = 'confidence';

        return [$this->stepFor($state, $lang), $state];
    }

    private function onConfidence(array $state, string $choice, string $lang, array $t): array
    {
        if (!preg_match('/^goal_conf_([1-3])$/', $choice, $m)) {
            return [$this->stepFor($state, $lang, $t['pick']), $state];
        }

        $state['confidence'] = (int) $m[1];

        // Not sure it's doable: shrink it once, which is the proven way to make a goal stick.
        if ($state['confidence'] === 1 && empty($state['shrunk'])) {
            $state['phase'] = 'shrink';
            return [$this->stepFor($state, $lang), $state];
        }

        $state['phase'] = 'done';
        return [$this->doneStep($state, $lang), $state];
    }

    private function onShrink(array $state, string $free, string $lang, array $t): array
    {
        if (mb_strlen($free) < self::MIN_GOAL_LENGTH) {
            return [$this->stepFor($state, $lang, $t['retry_text']), $state];
        }

        $state['goal'] = mb_substr($free, 0, 140);
        $state['shrunk'] = true;
        $state['phase'] = 'done';

        return [$this->doneStep($state, $lang), $state];
    }

    // ------------------------------------------------------------------ record

    public function toRecord(array $state): ?array
    {
        if (empty($state['goal']) || empty($state['focus']) || empty($state['values'])) {
            return null;
        }

        return [
            'values' => $state['values'],
            'focus' => $state['focus'],
            'goal' => $state['goal'],
            'confidence' => $state['confidence'] ?? null,
        ];
    }

    public function saveRecord(int $userId, int $runId, array $record): void
    {
        $this->goals->create($userId, $record['values'], $record['focus'], $record['goal'], $record['confidence'], $runId);
    }

    // ------------------------------------------------------------------ steps

    /** The screen for the state's current phase, with an optional line in front. */
    private function stepFor(array $state, string $lang, ?string $prefix = null): ExerciseStep
    {
        $t = $this->t($lang);
        $say = fn(string $m) => trim(($prefix ? $prefix . "\n\n" : '') . $m);

        switch ($state['phase'] ?? 'values') {
            case 'checkin':
                return new ExerciseStep($say(sprintf($t['ci'], $state['ci_goal'] ?? '')), 'buttons', $this->options($t['ci_opts']), false, 0, self::TOTAL);

            case 'ci_next':
                $done = ($state['ci'] ?? 'not') === 'done';
                return new ExerciseStep($say($done ? $t['ci_next_done'] : $t['ci_next_open']), 'buttons',
                    $this->options($done ? $t['ci_next_opts_done'] : $t['ci_next_opts_open']), false, 0, self::TOTAL);

            case 'focus':
                $opts = [];
                foreach ($state['values'] ?? [] as $v) {
                    $opts['goal_focus_' . $v] = self::valueLabel($v, $lang);
                }
                return new ExerciseStep($say($t['focus']), 'buttons', $this->options($opts), false, 1, self::TOTAL);

            case 'goal':
                $opts = [];
                foreach ($this->suggestions($state['focus'] ?? '', $lang) as $i => $s) {
                    $opts['goal_pick_' . ($i + 1)] = $s;
                }
                $opts['goal_own'] = $t['goal_own'];
                return new ExerciseStep($say(sprintf($t['goal'], self::valueLabel($state['focus'] ?? '', $lang))), 'buttons', $this->options($opts), false, 2, self::TOTAL);

            case 'own':
                return new ExerciseStep($say($t['own']), 'text_input', [], false, 2, self::TOTAL);

            case 'confidence':
                return new ExerciseStep($say(sprintf($t['confidence'], $state['goal'] ?? '')), 'buttons', $this->options($t['conf_opts']), false, 3, self::TOTAL);

            case 'shrink':
                return new ExerciseStep($say($t['shrink']), 'text_input', [], false, 3, self::TOTAL);

            default: // values
                $items = [];
                foreach (self::VALUE_IDS as $id) {
                    $items[] = ['id' => $id, 'label' => self::valueLabel($id, $lang)];
                }
                return new ExerciseStep($say($t['values']), 'values_picker', [['id' => 'goal_vals:', 'label' => $t['continue']]], false, 0, self::TOTAL,
                    ['items' => $items, 'max' => self::MAX_VALUES, 'min' => 1]);
        }
    }

    private function doneStep(array $state, string $lang): ExerciseStep
    {
        $t = $this->t($lang);

        if (!empty($state['kept'])) {
            $body = sprintf($t['kept'], $state['ci_goal'] ?? '');
        } elseif (!empty($state['goal'])) {
            $labels = array_map(fn($v) => self::valueLabel($v, $lang), $state['values'] ?? []);
            $close = $this->voice->line('goal_close', self::NAME,
                'Their core values: ' . implode(', ', $labels) . '. Their goal for the week: "' . $state['goal'] . '".'
                . (empty($state['shrunk']) ? '' : ' They made it smaller so it feels doable.'), $lang) ?? $t['bye'];
            $body = $close . "\n\n" . sprintf($t['saved'], $state['goal']);
        } else {
            $body = $t['stopped'];
        }

        return new ExerciseStep($body . "\n\n" . $t['ask'], 'emoji_slider', [], true, self::TOTAL, self::TOTAL);
    }

    /** @return array<int, string> */
    private function suggestions(string $value, string $lang): array
    {
        return (self::GOALS[$lang] ?? self::GOALS['en'])[$value] ?? [];
    }

    /** @return array<int, array{id: string, label: string}> */
    private function options(array $map): array
    {
        $out = [];
        foreach ($map as $id => $label) {
            $out[] = ['id' => (string) $id, 'label' => $label];
        }
        return $out;
    }

    private function t(string $lang): array
    {
        return self::TEXT[$lang] ?? self::TEXT['en'];
    }
}
