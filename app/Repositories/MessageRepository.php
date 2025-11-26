<?php

namespace App\Repositories;

use App\Interfaces\MessageRepositoryInterface;
use App\Models\Message;

class MessageRepository implements MessageRepositoryInterface
{
    public function createMessage(array $data)
    {
        return Message::create($data);
    }

    public function getConversationHistory($sessionId, $limit = 10)
    {
        $messages = Message::where('session_id', $sessionId)
        ->latest()
        ->limit($limit)
        ->get();

        return $messages->reverse()->values();
    }
}