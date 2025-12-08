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

    public function handleUserMessage($sessionId, $userText = null, $audioFile = null, $userLang = 'en')
    {
        $inputType = 'text';
        $userAudioPath = null;
        $finalContent = $userText;

        // --- 1. HANDLE VOICE INPUT (WHISPER) ---
        if ($audioFile) {
            $inputType = 'audio';
            $path = $audioFile->store('voice_messages', 'public');
            $userAudioPath = $path;
            
            // Get absolute path for OpenAI
            $absolutePath = Storage::disk('public')->path($path);
            
            // Transcribe using Whisper
            $finalContent = $this->aiService->transcribe($absolutePath);
        }

        // --- 2. SAVE USER MESSAGE ---
        $userMessage = $this->messageRepo->createMessage([
            'session_id' => $sessionId,
            'sender' => 'user',
            'type' => $inputType,
            'content' => $finalContent, // Stores the TRANSCRIPT or Text
            'audio_path' => $userAudioPath,
            'is_crisis' => false,
        ]);

        // --- 3. CHECK FOR HUMAN LISTENER HANDOVER ---
        // If a human is assigned, we STOP here. The AI should not reply.
        $session = Session::find($sessionId);
        
        if ($session && $session->active_listener_id) {
            // Logic: User message is saved. Human listener sees it in dashboard.
            // We return the user message so frontend knows it was sent.
            return $userMessage;
        }

        // --- 4. CRISIS DETECTION ---
        $detectedKeyword = $this->crisisService->detectCrisis($finalContent);
        
        if ($detectedKeyword) {
            // A. Log the Alert to Admin Dashboard
            $this->crisisService->logCrisis($sessionId, $detectedKeyword);
            
            // B. Get Standard Helpline Text
            $crisisResponse = $this->crisisService->getCrisisResponse();
            
            // C. Save AI Message with IS_CRISIS = TRUE (Triggers Red Alert on Frontend)
            return $this->messageRepo->createMessage([
                'session_id' => $sessionId,
                'sender' => 'ai',
                'type' => 'text',
                'content' => $crisisResponse,
                'is_crisis' => true, 
            ]);
        }

        // --- 5. PREPARE AI CONTEXT (MULTILINGUAL) ---
        $dbHistory = $this->messageRepo->getConversationHistory($sessionId);
        
        // Dynamic Prompt with Language Instruction
        $dynamicPrompt = $this->systemPrompt . " [IMPORTANT: The user's preferred language is '{$userLang}'. Detect if they speak a different language and adapt, but default to '{$userLang}'.]";
        
        $formattedHistory = [['role' => 'system', 'content' => $dynamicPrompt]];

        foreach ($dbHistory as $msg) {
            $formattedHistory[] = [
                'role' => ($msg->sender === 'user') ? 'user' : 'assistant',
                'content' => $msg->content
            ];
        }

        // --- 6. GET AI TEXT RESPONSE ---
        $aiText = $this->aiService->generateChatResponse($formattedHistory);

        // --- 7. HANDLE AI VOICE OUTPUT (TTS) ---
        $aiAudioPath = null;
        
        // Only speak back if the user spoke to us first
        if ($inputType === 'audio') {
            $aiAudioPath = $this->aiService->speak($aiText);
        }

        // --- 8. SAVE AI MESSAGE ---
        $aiMessage = $this->messageRepo->createMessage([
            'session_id' => $sessionId,
            'sender' => 'ai',
            'type' => $inputType, 
            'content' => $aiText,
            'audio_path' => $aiAudioPath,
            'is_crisis' => false,
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
