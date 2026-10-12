<?php

namespace App\Domain\Exercises;

use App\Services\SleepLogService;

/**
 * Sleep Wind-down: a quick sleep log, an evening checklist, then a guided body scan or story.
 *
 * Phases: log_quality -> log_hours -> checklist -> mode -> session -> done.
 * The log questions are skipped when last night is already logged. The app plays the session
 * (line by line, optionally read aloud); the server only finishes it once enough of the script
 * has really elapsed. With EXERCISE_AI_ENABLED the app adds a short personal reaction to how the
 * user slept and a goodnight line; otherwise (or on any AI failure) fixed lines are used.
 */
class SleepWinddownExercise implements Exercise, ProducesRecord
{
    public const ID = 'sleep_winddown_01';
    private const NAME = 'Sleep Wind-down';
    private const TOTAL = 5;
    private const MIN_SESSION_SHARE = 0.5; // the user may fall asleep early, but not tap straight through

    private const CHECKLIST_IDS = ['screens', 'bedtime', 'caffeine', 'room', 'worries'];
    private const HOURS = ['sleep_h_4.5' => 4.5, 'sleep_h_5.5' => 5.5, 'sleep_h_6.5' => 6.5, 'sleep_h_7.5' => 7.5, 'sleep_h_8.5' => 8.5];
    private const QUALITY = ['sleep_q_1' => 1, 'sleep_q_2' => 2, 'sleep_q_3' => 3];

    private const TEXT = [
        'en' => [
            'q' => "Before we wind down, a quick check-in. How did you sleep last night?",
            'q_opts' => ['sleep_q_1' => 'Poorly', 'sleep_q_2' => 'Okay', 'sleep_q_3' => 'Well', 'sleep_skip' => 'Skip'],
            'q_react' => [1 => "I'm sorry it was a rough night. Let's make tonight gentler.", 2 => "Thanks for telling me. Let's help tonight be even better.", 3 => "Glad to hear it. Let's keep that going."],
            'h' => "About how many hours did you sleep?",
            'h_opts' => ['sleep_h_4.5' => 'Under 5', 'sleep_h_5.5' => '5 to 6', 'sleep_h_6.5' => '6 to 7', 'sleep_h_7.5' => '7 to 8', 'sleep_h_8.5' => '8 or more'],
            'chk' => "Let's set up for a good night. Tick what you've done, or will do, tonight.",
            'chk_items' => [
                'screens' => 'Screens off (or dimmed) 30 minutes before bed',
                'bedtime' => 'Going to bed at my usual time',
                'caffeine' => 'No caffeine this evening',
                'room' => 'Room dark, quiet and cool',
                'worries' => 'Written down anything on my mind',
            ],
            'continue' => 'Continue',
            'mode' => "What would you like tonight?",
            'mode_opts' => ['sleep_mode_body' => 'Body scan', 'sleep_mode_story' => 'Sleep story'],
            'session' => "Lie down comfortably and dim your screen. I'll guide you, and you're welcome to fall asleep at any point, that's the whole idea.",
            'early' => "Stay with it a little longer. There's no rush, and you can fall asleep any time.",
            'pick' => "Please choose one of the options.",
            'finish' => "Goodnight",
            'bye' => "Sleep well. You've done what you can for tonight, and that's enough.",
            'habits' => "Tonight you set up %d of %d helpful habits.",
            'ask' => "How are you feeling right now?",
        ],
        'hi' => [
            'q' => "Aaram se pehle ek chhota sa sawaal. Kal raat aapki neend kaisi rahi?",
            'q_opts' => ['sleep_q_1' => 'Kharab', 'sleep_q_2' => 'Theek-thaak', 'sleep_q_3' => 'Achhi', 'sleep_skip' => 'Chhodiye'],
            'q_react' => [1 => "Mujhe afsos hai ki raat mushkil rahi. Chaliye aaj raat ko thoda behtar banate hain.", 2 => "Batane ke liye shukriya. Aaj raat aur behtar banate hain.", 3 => "Yeh sunke accha laga. Isse aise hi banaye rakhte hain."],
            'h' => "Aap lagbhag kitne ghante soye?",
            'h_opts' => ['sleep_h_4.5' => '5 se kam', 'sleep_h_5.5' => '5 se 6', 'sleep_h_6.5' => '6 se 7', 'sleep_h_7.5' => '7 se 8', 'sleep_h_8.5' => '8 ya zyada'],
            'chk' => "Chaliye ek achhi neend ki taiyaari karte hain. Jo aapne kiya hai ya aaj raat karenge, use chuniye.",
            'chk_items' => [
                'screens' => 'Sone se 30 minute pehle screen band (ya dim)',
                'bedtime' => 'Apne roz ke samay par sona',
                'caffeine' => 'Shaam ko caffeine nahi',
                'room' => 'Kamra andhera, shaant aur thanda',
                'worries' => 'Dimaag ki baatein likh li hain',
            ],
            'continue' => 'Aage badhein',
            'mode' => "Aaj raat aap kya sunna chahenge?",
            'mode_opts' => ['sleep_mode_body' => 'Body scan', 'sleep_mode_story' => 'Neend ki kahani'],
            'session' => "Aaram se letiye aur apni screen dim kar lijiye. Main aapko guide karunga, aur aap kabhi bhi so sakte hain, yahi toh maksad hai.",
            'early' => "Thoda aur rukiye. Koi jaldi nahi hai, aur aap kabhi bhi so sakte hain.",
            'pick' => "Kripya ek vikalp chuniye.",
            'finish' => "Shubh ratri",
            'bye' => "Aaram se soiye. Aapne aaj raat ke liye jo ho sakta tha kiya, aur yahi kaafi hai.",
            'habits' => "Aaj raat aapne %d mein se %d achhi aadatein apnayi.",
            'ask' => "Ab aap kaisa mehsoos kar rahe hain?",
        ],
    ];

    public function __construct(protected ExerciseVoice $voice, protected SleepLogService $log)
    {
    }

    public function id(): string
    {
        return self::ID;
    }

    public function start(string $lang, array $context = []): array
    {
        $t = $this->t($lang);

        // Last night already logged: go straight to the checklist.
        if (isset($context['user_id']) && $this->log->loggedOn((int) $context['user_id'], now()->toDateString())) {
            return [$this->checklistStep($t, 2), ['phase' => 'checklist']];
        }

        return [$this->qualityStep($t), ['phase' => 'log_quality']];
    }

    public function advance(array $state, string $inputKind, string $text, string $lang, array $context = []): array
    {
        $t = $this->t($lang);
        $phase = $state['phase'] ?? 'log_quality';
        $choice = $inputKind === self::INPUT_CHOICE ? trim($text) : '';

        return match ($phase) {
            'log_quality' => $this->onQuality($state, $choice, $lang, $t),
            'log_hours' => $this->onHours($state, $choice, $t),
            'checklist' => $this->onChecklist($state, $choice, $t),
            'mode' => $this->onMode($state, $choice, $lang, $t),
            default => $this->onSession($state, $inputKind, $lang, $t),
        };
    }

    // ------------------------------------------------------------------ phases

    private function onQuality(array $state, string $choice, string $lang, array $t): array
    {
        if ($choice === 'sleep_skip') {
            $state['phase'] = 'checklist';
            return [$this->checklistStep($t, 2), $state];
        }

        if (!isset(self::QUALITY[$choice])) {
            return [$this->qualityStep($t, $t['pick']), $state];
        }

        $quality = self::QUALITY[$choice];
        $state['quality'] = $quality;
        $state['phase'] = 'log_hours';

        $label = [1 => 'poorly', 2 => 'okay', 3 => 'well'][$quality];
        $reaction = $this->voice->line('sleep_react', self::NAME, "They said they slept $label last night.", $lang) ?? $t['q_react'][$quality];

        return [new ExerciseStep($reaction . "\n\n" . $t['h'], 'buttons', $this->options($t['h_opts']), false, 1, self::TOTAL), $state];
    }

    private function onHours(array $state, string $choice, array $t): array
    {
        if (!isset(self::HOURS[$choice])) {
            return [new ExerciseStep($t['pick'] . "\n\n" . $t['h'], 'buttons', $this->options($t['h_opts']), false, 1, self::TOTAL), $state];
        }

        $state['hours'] = self::HOURS[$choice];
        $state['phase'] = 'checklist';

        return [$this->checklistStep($t, 2), $state];
    }

    private function onChecklist(array $state, string $choice, array $t): array
    {
        if (!str_starts_with($choice, 'sleep_chk:')) {
            return [$this->checklistStep($t, 2, $t['pick']), $state];
        }

        $ids = array_filter(explode(',', substr($choice, strlen('sleep_chk:'))));
        $state['checklist'] = array_values(array_intersect(self::CHECKLIST_IDS, $ids)); // known ids only, fixed order
        $state['phase'] = 'mode';

        return [$this->modeStep($t), $state];
    }

    private function onMode(array $state, string $choice, string $lang, array $t): array
    {
        $mode = match ($choice) {
            'sleep_mode_body' => SleepScripts::BODY,
            'sleep_mode_story' => SleepScripts::STORY,
            default => null,
        };

        if ($mode === null) {
            return [$this->modeStep($t, $t['pick']), $state];
        }

        $lines = SleepScripts::lines($mode, $lang);
        $total = SleepScripts::totalSeconds($lines);
        $state['phase'] = 'session';
        $state['mode'] = $mode;
        $state['started_at'] = now()->timestamp;
        $state['script_seconds'] = $total;

        return [$this->sessionStep($t['session'], $t, $mode, $lines, $total), $state];
    }

    private function onSession(array $state, string $inputKind, string $lang, array $t): array
    {
        $mode = $state['mode'] ?? SleepScripts::BODY;
        $lines = SleepScripts::lines($mode, $lang);
        $total = (int) ($state['script_seconds'] ?? SleepScripts::totalSeconds($lines));

        // Typing, or finishing before enough of the script has played: keep the session going.
        $elapsed = now()->timestamp - (int) ($state['started_at'] ?? 0);
        if ($inputKind !== self::INPUT_NEXT || $elapsed < (int) floor($total * self::MIN_SESSION_SHARE)) {
            return [$this->sessionStep($t['early'], $t, $mode, $lines, $total), $state];
        }

        $state['phase'] = 'done';
        $done = count($state['checklist'] ?? []);

        $close = $this->voice->line('sleep_close', self::NAME,
            $done > 0
                ? "They finished a wind-down session and set up $done of " . count(self::CHECKLIST_IDS) . ' helpful sleep habits tonight.'
                : 'They finished a wind-down session.', $lang) ?? $t['bye'];

        $habits = $done > 0 ? sprintf($t['habits'], $done, count(self::CHECKLIST_IDS)) : null;
        $message = implode("\n\n", array_filter([$close, $habits, $t['ask']]));

        return [new ExerciseStep($message, 'emoji_slider', [], true, self::TOTAL, self::TOTAL), $state];
    }

    // ------------------------------------------------------------------ record

    public function toRecord(array $state): ?array
    {
        // Only a night that was actually logged becomes a sleep-log entry.
        if (!isset($state['hours'], $state['quality'])) {
            return null;
        }

        return [
            'log_date' => now()->toDateString(),
            'hours' => $state['hours'],
            'quality' => $state['quality'],
            'checklist' => $state['checklist'] ?? [],
        ];
    }

    public function saveRecord(int $userId, int $runId, array $record): void
    {
        $this->log->upsert($userId, $record['log_date'], (float) $record['hours'], (int) $record['quality'], $record['checklist'], $runId);
    }

    // ------------------------------------------------------------------ steps

    private function qualityStep(array $t, ?string $prefix = null): ExerciseStep
    {
        return new ExerciseStep(trim(($prefix ? $prefix . "\n\n" : '') . $t['q']), 'buttons', $this->options($t['q_opts']), false, 0, self::TOTAL);
    }

    private function checklistStep(array $t, int $progress, ?string $prefix = null): ExerciseStep
    {
        $items = [];
        foreach (self::CHECKLIST_IDS as $id) {
            $items[] = ['id' => $id, 'label' => $t['chk_items'][$id]];
        }

        return new ExerciseStep(
            trim(($prefix ? $prefix . "\n\n" : '') . $t['chk']),
            'sleep_checklist',
            [['id' => 'sleep_chk:', 'label' => $t['continue']]],
            false,
            $progress,
            self::TOTAL,
            ['items' => $items],
        );
    }

    private function modeStep(array $t, ?string $prefix = null): ExerciseStep
    {
        return new ExerciseStep(trim(($prefix ? $prefix . "\n\n" : '') . $t['mode']), 'buttons', $this->options($t['mode_opts']), false, 3, self::TOTAL);
    }

    private function sessionStep(string $message, array $t, string $mode, array $lines, int $total): ExerciseStep
    {
        return new ExerciseStep(
            $message,
            'sleep_session',
            [['id' => 'cbt_ready_' . self::ID, 'label' => $t['finish']]],
            false,
            4,
            self::TOTAL,
            ['mode' => $mode, 'lines' => $lines, 'total_seconds' => $total],
        );
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
