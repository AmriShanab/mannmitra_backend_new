<?php

namespace App\Services;

use App\Interfaces\MessageRepositoryInterface;

class ChatService
{
    protected $messageRepo;
    protected $aiService;

    protected $systemPrompt = "You are MannMitra, a compassionate and empathetic mental health companion for India. Listen actively, validate feelings, and provide gentle support. Keep responses concise (under 3 sentences) unless asked for more.";

    public function __construct(MessageRepositoryInterface $messageRepo, OpenAiService $aiService)
    {
        $this->messageRepo = $messageRepo;
        $this->aiService = $aiService;
    }

    public function handleUserMessage($sessionId, $userText)
    {
        $this->messageRepo->createMessage([
            'session_id' => $sessionId,
            'sender' => 'user',
            'type' => 'text',
            'content' => $userText
        ]);

        $dbHistory = $this->messageRepo->getConversationHistory($sessionId);

        $formattedHistory = [];

        $formattedHistory[] = ['role' => 'system', 'content' => $this->systemPrompt];

        foreach ($dbHistory as $msg) {
            $role = ($msg->sender === 'user') ? 'user' : 'assistant';
            $formattedHistory[] = ['role' => $role, 'content' => $msg->content];
        }

        $aiResponseText = $this->aiService->generateChatResponse($formattedHistory);

        $aiMessage = $this->messageRepo->createMessage([
            'session_id' => $sessionId,
            'sender' => 'ai',
            'type' => 'text',
            'content' => $aiResponseText,
        ]);

        return $aiMessage;
    }


    public function getMessages($sessionId)
    {
        return $this->messageRepo->getConversationHistory($sessionId, 50);
    }
}
