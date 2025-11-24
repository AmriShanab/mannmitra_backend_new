<?php

namespace App\Interfaces;

interface MoodRepositoryInterface
{
    public function createEntry($userId, array $data);
    public function getRecentEntries($userId, $limit = 20);
    public function hasEntryToday($userId);
    public function getEntriesByDateRange($userId, $startDate, $endDate);
}