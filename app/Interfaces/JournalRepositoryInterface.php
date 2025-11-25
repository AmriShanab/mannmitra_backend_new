<?php

namespace App\Interfaces;

interface JournalRepositoryInterface
{
    public function createEntry($userId, array $data);
    public function getUserEntries($userId, $limit = 10);
    public function getEntryById($userId, $entryId);
    public function deleteEntry($userId, $entryId);
}