<?php

namespace App\Domain\Exercises;

use App\Models\ExerciseRun;
use App\Models\MoodEntry;
use App\Repositories\CompanionRepository;
use App\Services\ExerciseAccessService;
use App\Support\Language;

/**
 * Runs state-machine exercises: starts them, advances the stored state, and completes the run
 * (consuming a paid session) when the exercise itself says it is finished.
 */
class ExerciseSessionService
{
    private const WRAP_UP_MINUTES = 15;

    public function __construct(
        protected ExerciseRegistry $registry,
        protected ExerciseAccessService $access,
        protected CompanionRepository $repo,
    ) {
    }

    /** True when this activity runs on the server-side state machine. */
    public function isManaged(string $activityId): bool
    {
        return config('openai.exercise_state_machine') && $this->registry->has($activityId);
    }

    /** The user's in-progress managed run, if any. */
    public function activeManagedRun($user): ?ExerciseRun
    {
        $run = $this->access->activeRun($user);
        return ($run && $this->isManaged($run->activity_id)) ? $run : null;
    }

    /**
     * Opening screen. The caller must already have created the run via ExerciseAccessService.
     */
    public function start($session, $user, string $activityId): array
    {
        $exercise = $this->registry->get($activityId);
        $mood = $this->recentMood($user);
        [$step, $state] = $exercise->start($this->lang($user), ['mood' => $mood, 'user_id' => $user->id]);
        $state['mood_before'] = $mood;

        $run = $this->access->activeRun($user, $activityId);
        $run?->update(['state' => $state, 'step' => $step->progress]);

        return $this->emit($session, $user, $step, $run);
    }

    /**
     * Next screen for an in-progress run. A run that has no stored state yet is started instead.
     */
    public function advance($session, $user, ExerciseRun $run, string $inputKind, string $text = ''): array
    {
        $exercise = $this->registry->get($run->activity_id);

        if ($run->state === null) {
            return $this->start($session, $user, $run->activity_id);
        }

        [$step, $state] = $exercise->advance($run->state, $inputKind, $text, $this->lang($user), ['user_id' => $user->id]);
        $run->update(['state' => $state, 'step' => $step->progress]);

        $node = $this->emit($session, $user, $step, $run);

        if ($step->completed) {
            $done = $this->access->completeActiveRun($user);
            if ($done) {
                $this->saveRecord($exercise, $user, $done, $state);
                $node['exercise_completed'] = true;
                $node['sessions_left'] = $done->free_pass ? null : $this->access->sessionsLeft($user, $done->activity_id);
            }
        }

        return $node;
    }

    /**
     * The mood slider shown at the end of an exercise. Replies with a canned wrap-up (no AI call)
     * once per finished run; returns null when the slider was not part of an exercise.
     */
    public function wrapUpMood($session, $user, $score): ?array
    {
        $run = ExerciseRun::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subMinutes(self::WRAP_UP_MINUTES))
            ->latest('id')
            ->first();

        if (!$run || !$this->isManaged($run->activity_id) || ($run->state['wrapped'] ?? false)) {
            return null;
        }

        $state = $run->state ?? [];
        $state['wrapped'] = true;
        $state['mood_after'] = is_numeric($score) ? (int) $score : null;
        $run->update(['state' => $state]);

        $low = is_numeric($score) && (int) $score <= 4;
        $message = Language::text($low ? 'companion.wrapup_low' : 'companion.wrapup_ok', $user->language);

        $this->repo->createMessage($session->id, 'ai', 'text', $message);

        return [
            'node_id' => 'ex_wrapup_' . time(),
            'ai_message' => $message,
            'ui_mode' => 'text_input',
            'options' => [],
        ];
    }

    /** Latest mood logged in the last 6 hours, so exercises can show a before/after change. */
    private function recentMood($user): ?int
    {
        $mood = MoodEntry::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHours(6))
            ->latest('id')
            ->value('primary_mood');

        return is_numeric($mood) ? (int) $mood : null;
    }

    private function saveRecord(Exercise $exercise, $user, ExerciseRun $run, array $state): void
    {
        if (!$exercise instanceof ProducesRecord) {
            return;
        }

        $record = $exercise->toRecord($state);
        if ($record) {
            $exercise->saveRecord($user->id, $run->id, $record);
        }
    }

    private function emit($session, $user, ExerciseStep $step, ?ExerciseRun $run): array
    {
        $this->repo->createMessage($session->id, 'ai', 'text', $step->message);

        return [
            'node_id' => 'ex_' . ($run?->activity_id ?? 'x') . '_' . time(),
            'ai_message' => $step->message,
            'ui_mode' => $step->uiMode,
            'options' => $step->options,
            'exercise' => [
                'id' => $run?->activity_id,
                'step' => $step->progress,
                'total_steps' => $step->total,
                'completed' => $step->completed,
            ] + $step->meta,
        ];
    }

    private function lang($user): string
    {
        // The exercise scripts exist in English and Hinglish; see Language::exerciseLang.
        return Language::exerciseLang($user->language);
    }
}
