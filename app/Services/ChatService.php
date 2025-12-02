<?php

namespace App\Services;

use App\Interfaces\MessageRepositoryInterface;
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
            
            // 1. Save the file first
            $path = $audioFile->store('voice_messages', 'public');
            $userAudioPath = $path;
            
            // 2. Get the ABSOLUTE path of the saved file
            // (OpenAI needs a real file path on your server, not a relative URL)
            $absolutePath = Storage::disk('public')->path($path);
            
            // 3. Pass the absolute path string, not the file object
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
}
