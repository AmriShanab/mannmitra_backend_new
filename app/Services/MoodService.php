<?php

namespace App\Services;

use App\Interfaces\MoodRepositoryInterface;
use Carbon\Carbon;

class MoodService
{
    protected $moodRepo;

    // Centralized Score Map
    protected $scoreMap = [
        'Happy' => 5,
        'Calm' => 4,
        'Neutral' => 3,
        'Sad' => 2,
        'Anxious' => 1,
    ];

    public function __construct(MoodRepositoryInterface $moodRepo)
    {
        $this->moodRepo = $moodRepo;
    }

    public function logMood($userId, array $validatedData)
    {
        return $this->moodRepo->createEntry($userId, $validatedData);
    }

    public function getTimeline($userId)
    {
        return $this->moodRepo->getRecentEntries($userId);
    }

    public function checkMondayRequirement($userId)
    {
        if (!Carbon::now()->isMonday()) {
            return false; // Not Monday, not required
        }
        // If it IS Monday, check if they already logged
        return !$this->moodRepo->hasEntryToday($userId);
    }

    public function generateDailySummary($userId, $days)
    {
        $days = max(1, min($days, 60));
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $entries = $this->moodRepo->getEntriesByDateRange($userId, $startDate, $endDate);

        // Group by Date and Calculate Average
        $buckets = [];
        foreach ($entries as $e) {
            $date = $e->created_at->format('Y-m-d');
            $score = $this->scoreMap[$e->primary_mood] ?? 3;

            if (!isset($buckets[$date])) $buckets[$date] = ['sum' => 0, 'count' => 0];
            $buckets[$date]['sum'] += $score;
            $buckets[$date]['count']++;
        }

        // Fill empty days with null
        $result = [];
        for ($i = 0; $i < $days; $i++) {
            $currentDay = $startDate->copy()->addDays($i);
            $dateKey = $currentDay->format('Y-m-d');

            $avg = null;
            if (isset($buckets[$dateKey])) {
                $avg = round($buckets[$dateKey]['sum'] / $buckets[$dateKey]['count'], 2);
            }

            $result[] = [
                'date' => $dateKey,
                'day_label' => $currentDay->format('D'),
                'average_mood_score' => $avg
            ];
        }

        return $result;
    }

    // ... inside MoodService class

    public function generateWeeklySummary($userId)
    {
        // 1. Logic: Define the time range (Last 12 weeks)
        $since = Carbon::now()->copy()->subWeeks(12)->startOfWeek(Carbon::MONDAY);

        // 2. Data: Fetch raw entries from Repo
        $entries = $this->moodRepo->getEntriesSince($userId, $since);

        // 3. Logic: Process the data (Group by Week)
        $buckets = [];

        foreach ($entries as $e) {
            $dt = Carbon::parse($e->created_at);
            $isoYear = $dt->isoWeekYear;
            $isoWeek = $dt->isoWeek;

            // Create a unique key like "2025-42"
            $key = $isoYear . '-' . str_pad((string)$isoWeek, 2, '0', STR_PAD_LEFT);

            // Use the centralized scoreMap (make sure it's defined at top of Service)
            $score = $this->scoreMap[$e->primary_mood] ?? 3;

            if (!isset($buckets[$key])) {
                $buckets[$key] = ['sum' => 0, 'count' => 0, 'week' => $isoWeek];
            }

            $buckets[$key]['sum'] += $score;
            $buckets[$key]['count'] += 1;
        }

        // 4. Logic: Sort and Format
        ksort($buckets, SORT_STRING);

        $result = [];
        foreach ($buckets as $data) {
            $avg = $data['count'] > 0 ? $data['sum'] / $data['count'] : 0;
            $result[] = [
                'week_label' => 'Wk ' . (int)$data['week'],
                'average_mood_score' => round($avg, 2)
            ];
        }

        return $result;
    }
}
