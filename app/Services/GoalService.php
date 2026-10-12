<?php

namespace App\Services;

use App\Models\UserGoal;
use Carbon\Carbon;

/**
 * One small weekly goal per user. Used by the Values & Goal exercise (which sets it and checks in
 * on it) and by the free "My goals" screen (where the user can also check in).
 */
class GoalService
{
    public const PROGRESS = ['done', 'partly', 'not'];
    private const GOAL_DAYS = 7;

    public function active(int $userId): ?UserGoal
    {
        return UserGoal::where('user_id', $userId)->where('status', 'active')->latest('id')->first();
    }

    /** The active goal when it was set before today, i.e. it is time to ask how it went. */
    public function dueForCheckIn(int $userId): ?UserGoal
    {
        $goal = $this->active($userId);

        return ($goal && $goal->created_at->startOfDay()->lt(now()->startOfDay())) ? $goal : null;
    }

    /** Creates a new weekly goal; any goal still active is closed as replaced. */
    public function create(int $userId, array $values, string $focus, string $text, ?int $confidence, ?int $runId = null): UserGoal
    {
        UserGoal::where('user_id', $userId)->where('status', 'active')->update(['status' => 'replaced']);

        return UserGoal::create([
            'user_id' => $userId,
            'exercise_run_id' => $runId,
            'core_values' => array_values($values),
            'focus_value' => $focus,
            'goal_text' => $text,
            'confidence' => $confidence,
            'status' => 'active',
            'due_date' => now()->addDays(self::GOAL_DAYS)->toDateString(),
        ]);
    }

    /** Records how the week went. "Done" completes the goal; otherwise it stays active. */
    public function checkIn(UserGoal $goal, string $progress): UserGoal
    {
        if (!in_array($progress, self::PROGRESS, true)) {
            return $goal;
        }

        $goal->update([
            'progress' => $progress,
            'checked_in_at' => now(),
            'status' => $progress === 'done' ? 'completed' : $goal->status,
        ]);

        return $goal;
    }

    /** Keeps the same goal for another week. */
    public function keep(UserGoal $goal): UserGoal
    {
        $goal->update(['due_date' => now()->addDays(self::GOAL_DAYS)->toDateString(), 'progress' => null, 'checked_in_at' => null]);
        // Re-stamp so the next check-in is a week from now, not tomorrow.
        $goal->forceFill(['created_at' => now()])->save();

        return $goal;
    }

    /** @return array{active: array|null, history: array} */
    public function overview(int $userId, string $lang): array
    {
        $goals = UserGoal::where('user_id', $userId)->latest('id')->limit(15)->get();
        $active = $goals->firstWhere('status', 'active');

        return [
            'active' => $active ? $this->present($active, $lang) : null,
            'history' => $goals->where('status', '!=', 'active')->map(fn($g) => $this->present($g, $lang))->values()->all(),
        ];
    }

    private function present(UserGoal $g, string $lang): array
    {
        $due = Carbon::parse($g->due_date)->startOfDay();

        return [
            'id' => $g->id,
            'goal' => $g->goal_text,
            'value' => $g->focus_value,
            'value_label' => \App\Domain\Exercises\ValuesGoalExercise::valueLabel($g->focus_value, $lang),
            'status' => $g->status,
            'progress' => $g->progress,
            'due_date' => $due->toDateString(),
            'days_left' => max(0, (int) now()->startOfDay()->diffInDays($due, false)),
            'set_on' => $g->created_at->toDateString(),
        ];
    }
}
