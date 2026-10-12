<?php

namespace App\Ai;

use App\Models\Subscription;
use Illuminate\Support\Facades\Cache;

/**
 * Caps how many AI-backed chat turns a user can spend per day (subscribers get a higher cap).
 * Exercises that run on the state machine do not use this budget.
 */
class AiBudgetService
{
    public function limitFor($user): int
    {
        $subscriber = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->exists();

        return (int) config($subscriber ? 'openai.daily_turns.subscriber' : 'openai.daily_turns.free');
    }

    /**
     * Spend one turn. Returns false (without spending) once the day's limit is reached.
     * A limit of 0 or less disables the cap.
     */
    public function consume($user): bool
    {
        $limit = $this->limitFor($user);
        if ($limit <= 0) {
            return true;
        }

        $key = $this->key($user);
        Cache::add($key, 0, now()->endOfDay()->addMinutes(5));
        $used = (int) Cache::increment($key);

        return $used <= $limit;
    }

    public function remaining($user): ?int
    {
        $limit = $this->limitFor($user);
        return $limit <= 0 ? null : max(0, $limit - (int) Cache::get($this->key($user), 0));
    }

    private function key($user): string
    {
        return 'ai_turns:' . $user->id . ':' . now()->toDateString();
    }
}
