<?php

namespace App\Services;

use App\Interfaces\MessageRepositoryInterface;
use App\Models\Session;
use Illuminate\Support\Facades\Storage;

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

    public function handleUserMessage($sessionId, $userText = null, $audioFile = null)
    {
        $inputType = 'text';
        $userAudioPath = null;
        $finalContent = $userText;

        if ($audioFile) {
            $inputType = 'audio';
            $path = $audioFile->store('voice_messages', 'public');
            $userAudioPath = $path;
            $absolutePath = Storage::disk('public')->path($path);
        
            $finalContent = $this->aiService->transcribe($absolutePath);
        }

        
        $this->messageRepo->createMessage([
            'session_id' => $sessionId,
            'sender' => 'user',
            'type' => $inputType,
            'content' => $finalContent, 
            'audio_path' => $userAudioPath,
        ]);

        $dbHistory = $this->messageRepo->getConversationHistory($sessionId);
        $formattedHistory = [['role' => 'system', 'content' => $this->systemPrompt]];

        foreach ($dbHistory as $msg) {
            $formattedHistory[] = [
                'role' => ($msg->sender === 'user') ? 'user' : 'assistant',
                'content' => $msg->content
            ];
        }

        $aiText = $this->aiService->generateChatResponse($formattedHistory);

        $aiAudioPath = null;

        if ($inputType === 'audio') {
            $aiAudioPath = $this->aiService->speak($aiText);
        }

        $aiMessage = $this->messageRepo->createMessage([
            'session_id' => $sessionId,
            'sender' => 'ai',
            'type' => $inputType, 
            'content' => $aiText,
            'audio_path' => $aiAudioPath,
        ]);

        return $aiMessage;
    }


    public function getMessages($sessionId)
    {
        return $this->messageRepo->getConversationHistory($sessionId, 50);
    }

    public function getUserSession($userId)
    {
        $session = Session::where('user_id', $userId)
        ->where('status', 'active')
        ->latest()
        ->first();

        if(!$session) {
            $session = Session::create([
                'user_id' => $userId,
                'status' => 'active',
            ]);

        }

        return $session;
    }

    public function handleUserMessageForUser($userId, $userText = null, $audioFile = null)
    {
        $session = $this->getUserSession($userId);
        return $this->handleUserMessage(
            $session->id, 
            $userText, 
            $audioFile
        );
    }

    public function getHistoryWithLimit($userId, $limit = 50)
    {
        $session = $this->getUserSession($userId);
        return $this->messageRepo->getConversationHistory($session->id, $limit);
    }
}
