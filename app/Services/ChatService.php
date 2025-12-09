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

    // --- UPDATED SYSTEM PROMPT FOR SMART DETECTION (JSON MODE) ---
    protected $systemPrompt = "
        You are MannMitra, an empathetic and supportive mental health companion.
        
        CRITICAL OUTPUT RULES:
        1. You MUST return valid JSON only.
        2. Format: { \"reply\": \"string\", \"is_crisis\": boolean, \"severity\": \"none|low|medium|high\", \"reason\": \"string\" }
        
        CRISIS DEFINITION:
        - Set 'is_crisis': true IF the user mentions: Suicide, Self-harm, Killing themselves, Dying, or immediate violence.
        
        NORMAL CONVERSATION:
        - Set 'is_crisis': false.
        - Your 'reply' should be warm, validating, non-judgmental.
        
        LANGUAGE PRIORITY (CRITICAL):
        1. FIRST PRIORITY: Detect the language of the user's CURRENT message (the one provided right now). You MUST reply in that EXACT same language.
        2. SECOND PRIORITY: Only if the user's input is unclear (e.g., emojis only) or silence, use their profile preference.
    ";

    public function __construct(MessageRepositoryInterface $messageRepo, OpenAiService $aiService, CrisisService $crisisService)
    {
        $this->messageRepo = $messageRepo;
        $this->aiService = $aiService;
        $this->crisisService = $crisisService;
    }

    public function handleUserMessage($sessionId, $userText = null, $audioFile = null, $userLang = 'en')
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

        $userMessage = $this->messageRepo->createMessage([
            'session_id' => $sessionId,
            'sender' => 'user',
            'type' => $inputType,
            'content' => $finalContent,
            'audio_path' => $userAudioPath,
            'is_crisis' => false,
        ]);

        $session = Session::find($sessionId);

        if ($session && $session->active_listener_id) {
            return $userMessage;
        }

        $dbHistory = $this->messageRepo->getConversationHistory($sessionId);
        $dynamicPrompt = $this->systemPrompt . " [Profile Fallback Language: '{$userLang}'. IF user types in English, reply English. IF user types in Hindi, reply Hindi.]";
        $formattedHistory = [['role' => 'system', 'content' => $dynamicPrompt]];
        foreach ($dbHistory as $msg) {
            $formattedHistory[] = [
                'role' => ($msg->sender === 'user') ? 'user' : 'assistant',
                'content' => $msg->content
            ];
        }

        $aiData = $this->aiService->generateChatResponse($formattedHistory);

        $aiText = $aiData['reply'] ?? "I am here to listen. Can you tell me more?";
        $isCrisis = $aiData['is_crisis'] ?? false;
        $severity = $aiData['severity'] ?? 'high';
        $reason = $aiData['reason'] ?? 'AI Detected Crisis';
        if ($isCrisis) {
            $this->crisisService->logCrisis($sessionId, $reason, $severity);

            // B. (Optional) Force Append Helpline if you don't trust the AI's reply fully
            // $aiText .= "\n\n" . $this->crisisService->getHelplineText();
        }

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
            'is_crisis' => $isCrisis,
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

        if (!$session) {
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
        $userLang = $session->user->language ?? 'en';

        return $this->handleUserMessage(
            $session->id,
            $userText,
            $audioFile,
            $userLang
        );
    }

    public function getHistoryWithLimit($userId, $limit = 50)
    {
        $session = $this->getUserSession($userId);
        return $this->messageRepo->getConversationHistory($session->id, $limit);
    }
}
