<?php

namespace App\Repositories;

use App\Interfaces\JournalRepositoryInterface;
use App\Models\JournalEntry;

class JournalRepository implements JournalRepositoryInterface
{
    public function createEntry($userId, array $data)
    {
        return JournalEntry::create(array_merge($data, ['user_id' => $userId]));
    }

    public function getUserEntries($userId, $limit = 10)
    {
        return JournalEntry::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }

    public function getEntryById($userId, $entryId)
    {
        return JournalEntry::where('user_id', $userId)
            ->where('id', $entryId)
            ->firstOrFail();
    }

    public function deleteEntry($userId, $entryId)
    {
        $entry = $this->getEntryById($userId, $entryId);
        $entry->delete();
    }

    public function getEntriesFromDateRange($userId, $startDate, $endDate)
    {
        return JournalEntry::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'asc')
            ->get(['id', 'content', 'created_at']); // <--- Added 'id'
    }


    public function updateReflection($entryId, $reflection)
    {
        // Find the specific journal entry by its unique ID
        $entry = JournalEntry::findOrFail($entryId);

        // Update the column
        $entry->ai_reflection = $reflection;
        $entry->save();

        return $entry;
    }
}
