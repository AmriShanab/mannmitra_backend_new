<?php

namespace App\Interfaces;

interface MessageRepositoryInterface
{
    public function createMessage(array $data);
    public function getConversationHistory($sessionId, $limit = 10);
}