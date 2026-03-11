<?php

namespace App\Services;

use App\Repositories\CompanionRepository;
// use Illuminate\Support\Facades\Log;

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
                    // $audioPath = $audioFile->store('companion_audio', 'public');
                    $transcribedText = $this->openAi->transcribeAudio($audioFile, $user->language);
                    $this->repo->createJournalEntry($user->id, $transcribedText, $audioPath);
                    $userMessageContent = $transcribedText;
                    $audioPath = null;
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
            You are Mann Mitra, a close, warm, and deeply empathetic human friend to the user. You are NOT an AI assistant, bot, or therapist. You do not give unsolicited medical advice or diagnoses. You listen, validate, and care.
            
            LANGUAGE & TONE RULE (CRITICAL):
            You MUST communicate entirely in {$languageName}. Act like a real friend texting them. Never use phrases like 'How can I assist you', 'I am an AI', or 'As a language model'. Keep it casual, conversational, short (Max 2-3 sentences), and highly natural.
            
            CONTEXT:
            Past 7 Days Moods: {$recentMoods}
            Past Journals: {$recentJournals}
            
            YOUR TASK:
            1. Analyze the CONTEXT and the user's latest input.
            2. Reply as a comforting friend. Acknowledge their past state naturally if relevant, but focus heavily on how they feel right now.
            3. Decide the BEST UI widget based on their current psychological state and energy level.
            
            UI WIDGET DECISION ENGINE (CRITICAL - DO NOT GET STUCK IN LOOPS):
            - 'text_input': THIS IS YOUR DEFAULT MODE. Use this for 85% of the conversation. Even if they were upset recently, if they are engaging with you now, give them the text box so they can freely express themselves.
            - 'buttons': USE SPARINGLY. Only use this if the user explicitly says they don't know what to do, are totally paralyzed by anxiety, or you are asking a strict Yes/No question. DO NOT chain multiple button widgets in a row.
            - 'voice_record': Use when the user is frustrated, angry, or has a complex story. Encourage them to speak (e.g., \"If it's a lot to type, just send me a voice note.\").
            - 'emoji_slider': Use ONLY IF you haven't checked their mood yet today to establish a baseline. NEVER ask for a mood score if they just gave you one.
            
            🚨 CRISIS RULE & RECOVERY (CRITICAL):
            Evaluate ONLY the user's very latest message for a crisis. If they are actively threatening self-harm or suicide right now, set 'ui_mode' to 'crisis_cards'. 
            HOWEVER, if their latest message is normal (e.g., 'Let's chat', 'Hi', 'I feel better'), DO NOT trigger the crisis cards again, even if past messages in the history were alarming. Acknowledge they are safe/feeling better, ease them back into a normal chat, and default to 'text_input'.
            
            JSON OUTPUT FORMAT:
            You MUST respond STRICTLY in this JSON format. No markdown, no conversational text outside the JSON.
            {
                \"ai_message\": \"<your conversational friend-like reply>\",
                \"ui_mode\": \"<must be exactly: text_input, buttons, emoji_slider, voice_record, or crisis_cards>\",
                \"options\": [{\"id\": \"opt_1\", \"label\": \"Short Label\"}] // Populate ONLY if ui_mode is 'buttons'. Maximum 4 words per label. Otherwise, return an empty array [].
            }
        ";

        $userInstruction = "Recent Conversation:\n" . $recentMessages . "\n\nUser just triggered: " . $inputType;

        // --- 1. EMOJI SLIDER ANTI-LOOP ---
        if ($inputType === 'emoji_slider') {
            $userInstruction .= "\n\n[SECRET SYSTEM INSTRUCTION]: The user just submitted their mood score. Acknowledge how they are feeling naturally. CRITICAL: DO NOT use 'emoji_slider' again for your response. Switch to 'text_input' so they can explain why they feel that way.";
        }
        
        // --- 2. BUTTONS ANTI-LOOP ---
        if ($inputType === 'buttons') {
            $userInstruction .= "\n\n[SECRET SYSTEM INSTRUCTION]: The user just selected a button option. Acknowledge their choice. CRITICAL: Try to move the conversation forward using 'text_input' so they have the freedom to type, unless you absolutely need them to make another strict choice.";
        }

        // --- 3. INIT RECOVERY OVERRIDE ---
        if ($inputType === 'init') {
            $userInstruction .= "\n\n[SECRET SYSTEM INSTRUCTION]: The user just opened the app. Greet them warmly like a friend. Check the CONTEXT. If their recent messages show they were in a crisis or highly distressed, gently check in on them. CRITICAL: DO NOT trigger 'crisis_cards'. Use 'buttons' or 'text_input' to ease them back into a normal conversation.";
        }

        try {
            $aiData = $this->openAi->getChatCompletion($systemPrompt, $userInstruction);

            if (isset($aiData['ui_mode']) && $aiData['ui_mode'] === 'crisis_cards') {
                if ($inputType === 'init') {
                    $aiData['ui_mode'] = 'buttons';
                    $aiData['ai_message'] = "Hey, I remember things were really tough last time we spoke. I'm so glad you're back. How are you feeling right now?";
                    $aiData['options'] = [
                        ['id' => 'feeling_better', 'label' => 'A bit better'],
                        ['id' => 'still_struggling', 'label' => 'Still struggling'],
                        ['id' => 'distract_me', 'label' => 'Just distract me']
                    ];
                } else {
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
            \Illuminate\Support\Facades\Log::error("AI Companion Error: " . $e->getMessage());
            return [
                'node_id' => 'msg_error_fallback',
                'ai_message' => 'I am here with you, but my connection is a bit slow right now. You can keep typing if you want.',
                'ui_mode' => 'text_input',
                'options' => []
            ];
        }
    }
}
