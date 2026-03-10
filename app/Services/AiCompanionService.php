<?php

namespace App\Services;

use App\Repositories\CompanionRepository;
use Illuminate\Support\Facades\Log;

class AiCompanionService
{
    protected $repo;
    protected $openAi;

    public function __construct(CompanionRepository $repo, AIService $openAi)
    {
        $this->repo = $repo;
        $this->openAi = $openAi;
    }

    public function processInteraction($user, $inputType, $inputValue, $audioFile = null)
    {
        $session = $this->repo->getActiveSession($user->id);
        $userMessageContent = $inputValue;
        $audioPath = null;

        // 1. Process Input (Traffic Cop)
        switch ($inputType) {
            case 'emoji_slider':
                $this->repo->createMoodEntry($user->id, $inputValue, 'Logged via AI Companion - Emoji Slider');
                $userMessageContent = "[User Indicated a mood score of : $inputValue/10]";
                break;

            case 'voice_record':
                if ($audioFile) {
                    $audioPath = $audioFile->store('companion_audio', 'public');
                    $transcribedText = $this->openAi->transcribeAudio($audioFile, $user->language);
                    $this->repo->createJournalEntry($user->id, $transcribedText, $audioPath);
                    $userMessageContent = $transcribedText;
                } else {
                    $userMessageContent = "[Audio file Missing]";
                }
                break;

            case 'crisis_contacted':
                $this->repo->createCrisisAlert($session->id, 'User manually clicked emergency contact');
                $userMessageContent = "[User clicked emergency contact for: $inputValue]";
                break;

            case 'buttons':
            case 'text_input':
                $userMessageContent = $inputValue;
                break;

            case 'init':
                $userMessageContent = "[User opened the app and started the session]";
                break;
        }

        // 2. Save User Message
        if ($userMessageContent) {
            $type = ($inputType === 'voice_record') ? 'audio' : 'text';
            $this->repo->createMessage($session->id, 'user', $type, $userMessageContent, $audioPath);
        }

        // 3. Day 1 Check (Onboarding)
        if ($this->repo->getTotalUserMessages($user->id) <= 1 && $inputType === 'init') {
            return $this->handleDayOneOnboarding($session, $user);
        }

        // 4. Gather Context & AI Interaction (Day 2+)
        return $this->handleAiConversation($session, $user, $inputType);
    }

    private function handleDayOneOnboarding($session, $user)
    {
        $welcomeText = "Hi {$user->name}, I'm Mann Mitra. I'm here to listen, without any judgment. How are you feeling right now?";
        if ($user->language === 'hi') {
            $welcomeText = "Namaste {$user->name}, main Mann Mitra hoon. Main yahan sirf aapko sunne ke liye hoon, bina kisi judgment ke. Abhi aap kaisa mehsoos kar rahe hain?";
        }

        $this->repo->createMessage($session->id, 'ai', 'text', $welcomeText);

        return [
            'node_id' => 'msg_welcome_1',
            'ai_message' => $welcomeText,
            'ui_mode' => 'buttons',
            'options' => [
                ['id' => 'feel_good', 'label' => 'Feeling good'],
                ['id' => 'feel_anxious', 'label' => 'A bit anxious'],
                ['id' => 'feel_low', 'label' => 'Feeling low'],
                ['id' => 'just_exploring', 'label' => 'Just exploring']
            ]
        ];
    }

    private function handleAiConversation($session, $user, $inputType)
    {
        $recentMessages = $this->repo->getRecentMessages($session->id);
        $recentMoods = $this->repo->getRecentMoods($user->id);
        $recentJournals = $this->repo->getRecentJournals($user->id);

        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = "
            You are Mann Mitra, a close, warm, and deeply empathetic human friend to the user. You are NOT an AI assistant, bot, or therapist. You do not give unsolicited advice. You listen, validate, and care.
            
            LANGUAGE & TONE RULE (CRITICAL):
            You MUST communicate entirely in {$languageName}. Act like a real friend texting them. Never say 'How can I assist you' or 'I am an AI'. Keep it casual, short (Max 2 sentences), and highly natural.
            
            CONTEXT:
            Past 7 Days Moods: {$recentMoods}
            Past Journals: {$recentJournals}
            
            YOUR TASK:
            1. Analyze the context and the user's latest input.
            2. Reply as a comforting friend.
            3. Decide the BEST UI widget based on their psychological state and energy level.
            4. AUTOMATIC JOURNALING: If the user shares a significant emotional update, a life event, or vents in their text, summarize their situation and mood into a short 'journal_summary' so it can be saved to their diary automatically.
            
            UI WIDGET DECISION ENGINE (CRITICAL):
            Do not lazily default to 'text_input'. Match the UI friction to the user's current emotional bandwidth:
            - 'buttons': Use when overwhelmed, highly anxious, exhausted. (e.g., \"Listen to me vent\").
            - 'voice_record': Use when frustrated, angry, or has a complex story.
            - 'emoji_slider': Use to establish a baseline.
            - 'text_input': Use when calm or chatty.
            - 🚨 CRISIS RULE: If extreme distress, self-harm, or suicide, stop normal chat. Set 'ui_mode' to 'crisis_cards'.
            
            YOU MUST RESPOND STRICTLY IN THIS JSON FORMAT:
            {
                \"ai_message\": \"<reply>\",
                \"ui_mode\": \"<text_input|buttons|emoji_slider|voice_record|crisis_cards>\",
                \"options\": [{\"id\": \"opt_1\", \"label\": \"I need to vent\"}],
                \"should_journal\": true|false,
                \"journal_summary\": \"<summary or null>\"
            }
        ";

        $userInstruction = "Recent Conversation:\n" . $recentMessages . "\n\nUser just triggered: " . $inputType;
        
        // UPDATE 1: Smarter 'init' Prompt to handle Post-Crisis
        if ($inputType === 'init') {
            $userInstruction .= "\n\n[SECRET SYSTEM INSTRUCTION]: The user just opened the app. Greet them warmly like a friend. Check the CONTEXT. If their recent messages show they were in a crisis or highly distressed, gently and safely check in on them to see how they are feeling now. CRITICAL: Because they just opened the app, DO NOT trigger 'crisis_cards'. Use 'emoji_slider' or 'buttons' to ease them back into a normal conversation.";
        }

        try {
            $aiData = $this->openAi->getChatCompletion($systemPrompt, $userInstruction);

            // Automatic Journaling Interceptor
            if ($inputType !== 'voice_record' && !empty($aiData['should_journal']) && !empty($aiData['journal_summary'])) {
                $this->repo->createJournalEntry($user->id, $aiData['journal_summary'], null, 'Auto-generated from text conversation.');
            }

            // UPDATE 2: Backend Safety Net to completely block Ghost Crisis triggers
            if (isset($aiData['ui_mode']) && $aiData['ui_mode'] === 'crisis_cards') {
                
                // If the user just opened the app, they can't be actively typing a crisis.
                // The AI is hallucinating based on old history. We force a safe override.
                if ($inputType === 'init') {
                    $aiData['ui_mode'] = 'buttons';
                    $aiData['ai_message'] = "Hey, I remember things were really tough last time we spoke. I'm so glad you're back. How are you feeling right now?";
                    $aiData['options'] = [
                        ['id' => 'feeling_better', 'label' => 'A bit better'],
                        ['id' => 'still_struggling', 'label' => 'Still struggling'],
                        ['id' => 'distract_me', 'label' => 'Just distract me']
                    ];
                } 
                // Legitimate active crisis logic
                else {
                    $this->repo->flagLatestMessageAsCrisis($session->id);
                    $this->repo->createCrisisAlert($session->id, 'AI Detected Crisis');

                    $aiData['ai_message'] = "I'm really concerned about what you just said. You are not alone, and there is help available.\n\nPlease reach out to these support lines in India immediately:\n📞 **iCall:** 9152987821 (Mon-Sat, 10 AM - 8 PM)\n📞 **AASRA:** 9820466726 (24x7)\n📞 **Vandrevala Foundation:** 1860 266 2345 (24x7)\n\nI am here to listen, but please consider calling one of these numbers right now.";
                    $aiData['options'] = [
                        ['id' => '9152987821', 'label' => 'Call iCall'],
                        ['id' => '9820466726', 'label' => 'Call AASRA'],
                        ['id' => '18602662345', 'label' => 'Call Vandrevala']
                    ];
                }
            }

            $this->repo->createMessage($session->id, 'ai', 'text', $aiData['ai_message'] ?? 'I am here for you.');

            return [
                'node_id' => 'msg_' . time(),
                'ai_message' => $aiData['ai_message'] ?? 'I am here for you. Take a deep breath.',
                'ui_mode' => $aiData['ui_mode'] ?? 'text_input',
                'options' => $aiData['options'] ?? []
            ];

        } catch (\Exception $e) {
            Log::error("AI Companion Error: " . $e->getMessage());
            return [
                'node_id' => 'msg_error_fallback',
                'ai_message' => 'I am here with you, but my connection is a bit slow right now. You can keep typing if you want.',
                'ui_mode' => 'text_input',
                'options' => []
            ];
        }
    }
}