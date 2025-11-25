<?php

namespace App\Services;

use App\Interfaces\JournalRepositoryInterface;
use Illuminate\Support\Facades\DB;

use function Symfony\Component\Clock\now;

class JournalService
{
    protected $journalRepo;

    public function __construct(JournalRepositoryInterface $journalRepo)
    {
        $this->journalRepo = $journalRepo;
    }

    public function createJournal($userId, array $validatedData)
    {
        return DB::transaction(function () use ($userId, $validatedData) {
            if(empty($validatedData['title'])) {
                $validatedData['title'] = 'Entry For '. now()->format('M d, Y');
            }

            // 2. Future Integration Point: 
            // Here you would call AI Service to generate 'ai_reflection' based on 'content'
            // $validatedData['ai_reflection'] = $this->aiService->analyze($validatedData['content']);

            return $this->journalRepo->createEntry($userId, $validatedData);
        });
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