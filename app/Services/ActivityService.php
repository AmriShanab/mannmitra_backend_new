<?php

namespace App\Services;

use App\Domain\Exercises\ExerciseRecommender;
use App\Models\Activities;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use App\Support\Language;

class ActivityService
{
    public const CACHE_KEY = 'activities.catalog';
    private const CACHE_SECONDS = 600;

    public function __construct(
        protected ExerciseAccessService $access,
        protected ExerciseRecommender $recommender,
    ) {
    }

    /** The static catalog (identical for everyone), cached. */
    private function catalog(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn() => Activities::all()->toArray());
    }

    public function getAllActivities(?User $user = null, ?string $lang = null)
    {
        $rows = $this->catalog();

        if (!$user) {
            return $rows;
        }

        $lang ??= $user->language;
        $objects = array_map(fn($r) => (object) $r, $rows);
        $access = $this->access->accessMap($user, $objects);
        $recommended = $this->recommender->pick($user, array_column($rows, 'activity_id'));

        return array_map(function ($row) use ($access, $lang, $recommended) {
            $t = $row['translations'][Language::normalize($lang)] ?? $row['translations'][Language::exerciseLang($lang)] ?? null;
            if ($t) {
                $row['title'] = $t['title'] ?? $row['title'];
                $row['description'] = $t['description'] ?? $row['description'];
            }
            unset($row['translations']);

            return array_merge($row, $access[$row['activity_id']] ?? [], [
                'recommended' => $row['activity_id'] === $recommended,
            ]);
        }, $rows);
    }
}
