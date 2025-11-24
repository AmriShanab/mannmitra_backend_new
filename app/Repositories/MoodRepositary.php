<?php

namespace App\Repositories;

use App\Interfaces\MoodRepositoryInterface;
use App\Models\MoodEntry;
use Illuminate\Support\Carbon;

class MoodRepositary implements MoodRepositoryInterface
{
    public function createEntry($userId, array $data)
    {
        return MoodEntry::create(array_merge($data, ['user_id' => $userId]));
    }

    public function getRecentEntries($userId, $limit = 20)
    {
        return MoodEntry::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function hasEntryToday($userId)
    {
        return MoodEntry::where('user_id', $userId)
        ->whereDate('created_at', Carbon::today()->toDateString())
        ->exists();
    }

    public function getEntriesByDateRange($userId, $startDate, $endDate)
    {
        return MoodEntry::where('user_id', $userId)
        ->whereBetween('created_at', [$startDate, $endDate])
        ->orderBy('created_at', 'asc')
        ->get();
    }

}
