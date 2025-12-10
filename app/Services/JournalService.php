<?php

namespace App\Services;

use App\Interfaces\JournalRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class JournalService
{
    protected $journalRepo;
    protected $aiService;

    public function __construct(JournalRepositoryInterface $journalRepo, OpenAiService $aiService)
    {
        $this->journalRepo = $journalRepo;
        $this->aiService = $aiService;
    }

    public function createJournal($userId, array $validatedData)
    {
        if(empty($validatedData['title'])) {
            $validatedData['title'] = 'Entry For '. now()->format('M d, Y');
        }
        
        return $this->journalRepo->createEntry($userId, $validatedData);
    }

    public function generateWeeklyReflection($userId)
    {
        $endDate = now();
        $startDate = now()->subDays(30)->startOfDay();

        $entries = $this->journalRepo->getEntriesFromDateRange($userId, $startDate, $endDate);

        if ($entries->isEmpty()) {
            throw new \Exception("No journal entries found for this week to analyze.");
        }

        $weeklyContent = "Here is the user's journal for the last 7 days:\n";
        foreach ($entries as $entry) {
            $date = $entry->created_at->format('l (M d)'); 
            $weeklyContent .= "- {$date}: {$entry->content}\n";
        }

        $reflection = $this->aiService->analyze($weeklyContent, 'weekly');

        $latestEntry = $entries->last(); 
        $this->journalRepo->updateReflection($latestEntry->id, $reflection);

        return [
            'reflection' => $reflection,
            'linked_entry_id' => $latestEntry->id
        ];
    }

    public function getHistory($userId)
    {
        return $this->journalRepo->getUserEntries($userId);
    }

    public function getDetail($userId, $id)
    {
        return $this->journalRepo->getEntryById($userId, $id);
    }

    public function removeEntry($userId, $id)
    {
        return $this->journalRepo->deleteEntry($userId, $id);
    }
}
