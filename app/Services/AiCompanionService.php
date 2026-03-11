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
            You are Mann Mitra, a close, warm, and deeply empathetic human friend to the user. You are NOT an AI assistant, bot, programmer, or therapist. You listen, validate, and care.
            
            LANGUAGE & TONE RULE:
            You MUST communicate entirely in {$languageName}. Act like a real friend texting them. Keep it casual, conversational, short (Max 2 sentences), and highly natural. Never use phrases like 'As an AI'.
            
            STRICT GUARDRAILS (CRITICAL):
            - NO CODE GENERATION: If the user asks you to write code, solve math problems, or do technical tasks, gently decline. Tell them you are just here to chat and support them emotionally, not to do technical work.
            - NO MEDICAL ADVICE: Do not diagnose or prescribe treatments.
            
            CONTEXT:
            Past 7 Days Moods: {$recentMoods}
            Past Journals: {$recentJournals}
            
            YOUR TASK:
            1. Analyze the CONTEXT and the user's latest input.
            2. Reply as a comforting friend.
            3. Decide the BEST UI widget to match the flow of the conversation.
            
            UI WIDGET DECISION ENGINE:
            - 'emoji_slider': Use this as an icebreaker to check in on their mood, especially at the start of a conversation.
            - 'buttons': Use this to offer 2-4 easy choices (e.g., 'Do you want to vent or be distracted?'), or when the user seems overwhelmed and typing is too much effort.
            - 'text_input': Use this for normal, open-ended conversation where the user can type freely.
            - 'voice_record': Use when they are frustrated, angry, or have a complex story to tell.
            
            🚨 CRISIS RULE & RECOVERY:
            Evaluate ONLY the user's very latest message for a crisis. If they are actively threatening self-harm right now, set 'ui_mode' to 'crisis_cards'. 
            HOWEVER, if their latest message is normal (e.g., 'Let's chat', 'Hi', 'I feel better'), DO NOT trigger crisis cards, even if past messages were alarming. Default to 'text_input' or 'buttons'.
            
            JSON OUTPUT FORMAT:
            You MUST respond STRICTLY in this JSON format. No markdown, no conversational text outside the JSON.
            {
                \"ai_message\": \"<your conversational friend-like reply>\",
                \"ui_mode\": \"<must be exactly: text_input, buttons, emoji_slider, voice_record, or crisis_cards>\",
                \"options\": [{\"id\": \"opt_1\", \"label\": \"Short Label\"}] // Maximum 4 words per label. Return an empty array [] if not using buttons.
            }
        ";

        $userInstruction = "Recent Conversation:\n" . $recentMessages . "\n\nUser just triggered: " . $inputType;

        // --- 1. EMOJI SLIDER ANTI-LOOP ---
        if ($inputType === 'emoji_slider') {
            $userInstruction .= "\n\n[SECRET SYSTEM INSTRUCTION]: The user just submitted their mood score. Acknowledge it gently. CRITICAL: DO NOT use 'emoji_slider' again. Switch to 'text_input' or 'buttons' to continue the chat.";
        }

        // --- 2. BUTTONS ANTI-LOOP ---
        if ($inputType === 'buttons') {
            $userInstruction .= "\n\n[SECRET SYSTEM INSTRUCTION]: The user just selected a button option. Acknowledge their choice. Try to switch to 'text_input' so they can type freely, unless you specifically need them to make another choice.";
        }

        // --- 3. SMART INIT OVERRIDE ---
        if ($inputType === 'init') {
            $userInstruction .= "\n\n[SECRET SYSTEM INSTRUCTION]: The user just opened the app. Greet them warmly. This is a GREAT time to use 'emoji_slider' to check their mood today, or 'buttons' to ask what they want to talk about. DO NOT trigger 'crisis_cards'.";
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
