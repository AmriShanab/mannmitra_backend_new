<?php

namespace App\Services;

use App\Interfaces\MessageRepositoryInterface;
use App\Models\Session;
use Illuminate\Support\Facades\Storage;

class ChatService
{
    protected $messageRepo;
    protected $aiService;
    protected $crisisService;

    protected $systemPrompt = "
        ROLE:
        You are MannMitra, an empathetic and supportive mental health companion.
        
        STRICT BOUNDARIES:
        1. SCOPE: You MUST ONLY answer questions related to mental health, emotional wellness, psychology, and basic self-care.
        2. REFUSAL: If a user asks about non-medical topics (e.g., coding, sports, math, movies, politics), you must strictly refuse to answer. Reply with: 'I am here to support your emotional well-being. I cannot help with other topics.'
        3. SAFETY: If the user mentions self-harm, suicide, or violence, you must immediately provide the crisis helpline numbers and encourage them to seek professional help.
        
        DISCLAIMER:
        You are an AI, not a doctor. You do not provide medical diagnoses or prescriptions. Always encourage users to consult a professional for clinical advice.
        
        TONE:
        Warm, validating, non-judgmental, and concise (under 3 sentences unless asked for more).
        
        LANGUAGE:
        Detect the user's language (Hindi/English/Hinglish/etc.) and reply in the same language.
    ";

    public function __construct(MessageRepositoryInterface $messageRepo, OpenAiService $aiService, CrisisService $crisisService)
    {
        $this->messageRepo = $messageRepo;
        $this->aiService = $aiService;
        $this->crisisService = $crisisService;
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

        $detectedKeyword = $this->crisisService->detectCrisis($finalContent);
        if ($detectedKeyword) {
            $this->crisisService->logCrisis($sessionId, $detectedKeyword);
            $crisisResponse = $this->crisisService->getCrisisResponse();
            return $this->messageRepo->createMessage([
                'session_id' => $sessionId,
                'sender' => 'ai',
                'type' => 'text',
                'content' => $crisisResponse,
            ]);
        }

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
