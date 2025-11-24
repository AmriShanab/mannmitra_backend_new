<?php
namespace App\Services;

use App\Interfaces\MoodRepositoryInterface;
use Carbon\Carbon;

class MoodService
{
    protected $moodRepo;

    // Centralized Score Map
    protected $scoreMap = [
        'Happy' => 5, 'Calm' => 4, 'Neutral' => 3, 'Sad' => 2, 'Anxious' => 1,
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
}