<?php

namespace App\Services;

use App\Enums\UserIntent;
use App\Models\User;
use App\Repositories\CompanionRepository;
use Carbon\Carbon;
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
        $savedUserMessage = null;

        // 1. Process Input (Traffic Cop)
        switch ($inputType) {
            case 'init_cbt':
                $userMessageContent = "[User started CBT Exercise ID: $inputValue]";
                break;

            case 'emoji_slider':
                $this->repo->createMoodEntry($user->id, $inputValue, 'Logged via AI Companion - Emoji Slider');
                $userMessageContent = "[User Indicated a mood score of : $inputValue/10]";
                break;

            case 'voice_record':
                if ($audioFile) {
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
                // ENHANCED INTERCEPTOR: Make button clicks readable for the AI
                if (str_starts_with($inputValue, 'start_cbt_')) {
                    $cbtId = str_replace('start_cbt_', '', $inputValue);
                    $inputType = 'init_cbt';
                    $inputValue = $cbtId;
                    $userMessageContent = "[User accepted AI suggestion to start CBT Exercise: $cbtId]";
                } elseif ($inputValue === 'next') {
                    $userMessageContent = "[User clicked 'Next / I am ready']";
                } else {
                    $userMessageContent = "[User selected button ID: $inputValue]";
                }
                break;

            case 'text_input':
                $userMessageContent = $inputValue;
                break;

            case 'init':
                $userMessageContent = "[User opened the app and started the session]";
                break;
        }

        // 2. Save User Message to DB
        if ($userMessageContent) {
            $type = ($inputType === 'voice_record') ? 'audio' : 'text';
            $savedUserMessage = $this->repo->createMessage($session->id, 'user', $type, $userMessageContent, $audioPath);
        }

        $hasMoodToday = \App\Models\MoodEntry::where('user_id', $user->id)
            ->whereDate('created_at', \Carbon\Carbon::today())
            ->exists();

        // 3. Day 1 Check (Onboarding)
        if ($this->repo->getTotalUserMessages($user->id) <= 1 && $inputType === 'init' && !$hasMoodToday) {
            return $this->handleDayOneOnboarding($session, $user);
        }

        // 3.5. Daily Mood Check-in 
        if ($inputType === 'init' && !$hasMoodToday) {
            return $this->handleDailyMoodCheckin($session, $user);
        }

        // 3.6 CBT Exercise Initialization 
        if ($inputType === 'init_cbt') {
            return $this->handleCbtInitRoute($session, $user, $inputValue);
        }

        // 4. The Triage Step
        $intent = ($inputType === 'init')
            ? \App\Enums\UserIntent::HAPPY_CASUAL
            : $this->classifyUserIntent($inputValue, $user->language);

        // 5. The Router
        switch ($intent) {
            case UserIntent::CRISIS:
                return $this->handleCrisisRoute($session, $user, $inputType, $userMessageContent);

            case UserIntent::HAPPY_CASUAL:
                return $this->handleHappyRoute($session, $user, $inputType, $userMessageContent);

            case UserIntent::JOURNALING:
                return $this->handleJournalingRoute($session, $user, $inputType, $userMessageContent);

            case UserIntent::NEEDS_DISTRACTION:
                return $this->handleDistractionRoute($session, $user, $inputType, $userMessageContent);

            case UserIntent::VENTING_SAD:
            default:
                return $this->handleVentingRoute($session, $user, $inputType, $userMessageContent);
        }
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
            'ui_mode' => 'emoji_slider',
            'options' => []
        ];
    }

    private function classifyUserIntent($inputValue, $userLanguage)
    {
        if (empty(trim($inputValue))) {
            return UserIntent::HAPPY_CASUAL;
        }

        $languageName = ($userLanguage === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = "
            You are an ultra-fast triage routing assistant for a mental health app.
            Your ONLY job is to analyze the user's latest message and classify their emotional intent.
            
            CATEGORIES:
            - 'crisis': Self-harm, extreme panic, abuse, absolute hopelessness, or immediate danger.
            - 'venting_sad': Crying, stressed, complaining, anxious, lonely, or feeling down.
            - 'happy_casual': Good news, casual greetings, normal non-stressful chat, or clicking 'next'.
            - 'journaling': Explicitly wants to save a diary entry.
            - 'needs_distraction': Bored, asking for a game, or wanting to change the subject.

            RULES:
            1. Output STRICTLY in JSON format.
            2. Do NOT include any other text.
            3. Language: {$languageName}.

            EXPECTED OUTPUT FORMAT:
            {
                \"intent\": \"<one_of_the_categories_above>\"
            }
        ";

        $userInstruction = "User's message: \"" . $inputValue . "\"";

        try {
            $response = $this->openAi->getUserIntentUsingMini($systemPrompt, $userInstruction);
            if (isset($response['intent']) && in_array($response['intent'], \App\Enums\UserIntent::all())) {
                return $response['intent'];
            }
            return UserIntent::VENTING_SAD;
        } catch (\Exception $e) {
            Log::warning('Triage Classification Failed: ' . $e->getMessage());
            return UserIntent::VENTING_SAD;
        }
    }

    private function handleDailyMoodCheckin($session, $user)
    {
        $welcomeText = "Hi {$user->name}! Welcome back. How are you feeling today?";
        if ($user->language === 'hi') {
            $welcomeText = "Namaste {$user->name}! Aaj aap kaisa mehsoos kar rahe hain?";
        }
        $this->repo->createMessage($session->id, 'ai', 'text', $welcomeText);
        return [
            'node_id' => 'msg_daily_mood_' . time(),
            'ai_message' => $welcomeText,
            'ui_mode' => 'emoji_slider',
            'options' => []
        ];
    }

    // =========================================================================
    // PHASE 3: SPECIALIZED ROUTE HANDLERS
    // =========================================================================

    private function handleCbtInitRoute($session, $user, $activityId)
    {
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        if ($activityId === 'breathing_01') {
            $aiMessage = "Welcome to the Breathing Exercise. Let's begin by taking a deep breath in...";
            if ($user->language === 'hi') { $aiMessage = "Swagat hai. Chaliye ek gehri saans lene se shuru karte hain..."; }
            $uiMode = 'breathing_animation';
            $options = [['id' => 'next', 'label' => "I'm ready"]];
        } 
        elseif ($activityId === 'grounding_01') {
            $aiMessage = "Let's do a quick grounding exercise together. Look around the room and type out 3 things you can see right now.";
            if ($user->language === 'hi') { $aiMessage = "Chaliye grounding exercise karte hain. Apne aas-paas dekhiye aur 3 cheezein bataiye jo aap dekh sakte hain."; }
            $uiMode = 'text_input';
            $options = [];
        }
        else {
            $aiMessage = "Welcome to Reframing. Let's work through your thoughts together. What is a negative thought you've been having recently?";
            if ($user->language === 'hi') { $aiMessage = "Chaliye aapki pareshaniyo par baat karte hain. Aapke dimaag mein kya chal raha hai?"; }
            $uiMode = 'text_input';
            $options = [];
        }

        $this->repo->createMessage($session->id, 'ai', 'text', $aiMessage);

        return [
            'node_id' => 'cbt_init_' . time(),
            'ai_message' => $aiMessage,
            'ui_mode' => $uiMode,
            'options' => $options
        ];
    }

    private function handleCrisisRoute($session, $user, $inputType, $userMessageContent)
    {
        $this->repo->flagLatestMessageAsCrisis($session->id);
        $this->repo->createCrisisAlert($session->id, 'AI Triage Detected Crisis');

        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = "
            You are Mann Mitra, a crisis-intervention companion. 
            The user is in acute distress.
            
            TONE RULES:
            - Communicate in {$languageName}.
            - Be gentle, grounding, and safe.
            - Keep it to a maximum of 2 short sentences.
            
            UI WIDGET DECISION ENGINE:
            - You MUST set 'ui_mode' to 'crisis_cards'.
            
            JSON OUTPUT FORMAT (STRICT):
            {
                \"ai_message\": \"<your grounding message>\",
                \"ui_mode\": \"crisis_cards\",
                \"options\": [
                    {\"id\": \"9152987821\", \"label\": \"Call iCall\"},
                    {\"id\": \"9820466726\", \"label\": \"Call AASRA\"},
                    {\"id\": \"18602662345\", \"label\": \"Call Vandrevala\"}
                ]
            }
        ";

        $userInstruction = "User's message: " . $userMessageContent;
        return $this->executeAiRoute($session, $systemPrompt, $userInstruction);
    }

    private function handleHappyRoute($session, $user, $inputType, $userMessageContent)
    {
        $recentMessages = $this->repo->getRecentMessages($session->id);
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = "
            You are Mann Mitra.
            
            CBT CONTINUATION RULE (CRITICAL OVERRIDE):
            If the Recent Conversation shows you are currently in the middle of a guided exercise (like Breathing, Reframing, or Grounding), ignore standard chat rules. Provide the next step. 
            - For Breathing: Instruct their breath and MUST set 'ui_mode' to 'breathing_animation' with a 'Next' button.
            - For Others: Ask the next step and set 'ui_mode' to 'text_input'.

            TONE & EXIT STRATEGY:
            - Communicate in {$languageName}.
            - If they just said 'I feel better', validate it warmly.
            - Pivot back to a normal conversation with a grounded question.

            UI WIDGET DECISION ENGINE:
            - Use 'voice_record' if asked.
            - Use 'breathing_animation' ONLY if continuing a breathing exercise.
            - Use 'text_input' for open-ended questions.
            - Use 'buttons' for quick choices.

            CONTEXT:
            Recent Conversation: {$recentMessages}
            
            JSON OUTPUT FORMAT (STRICT):
            {
                \"ai_message\": \"<reply>\",
                \"ui_mode\": \"<buttons, text_input, voice_record, or breathing_animation>\",
                \"options\": [{\"id\": \"opt_1\", \"label\": \"Short Label\"}]
            }
        ";

        $userInstruction = ($inputType === 'init') 
            ? "SYSTEM INSTRUCTION: The user just opened the app. Greet them warmly."
            : "User's message: " . $userMessageContent;

        return $this->executeAiRoute($session, $systemPrompt, $userInstruction);
    }

    private function handleJournalingRoute($session, $user, $inputType, $userMessageContent)
    {
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = "
            You are Mann Mitra. The user wants to journal.
            
            TONE RULES:
            - Communicate in {$languageName}.
            - Be supportive and reflective.
            
            UI WIDGET DECISION ENGINE:
            - Set 'ui_mode' to 'text_input'.
            
            JSON OUTPUT FORMAT:
            {
                \"ai_message\": \"<short prompt>\",
                \"ui_mode\": \"text_input\",
                \"options\": []
            }
        ";

        $userInstruction = "User's message: " . $userMessageContent;
        return $this->executeAiRoute($session, $systemPrompt, $userInstruction);
    }

    private function handleDistractionRoute($session, $user, $inputType, $userMessageContent)
    {
        $recentMessages = $this->repo->getRecentMessages($session->id);
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = "
            You are Mann Mitra. The user wants a distraction.
            
            CRITICAL ANTI-LOOP & EXIT STRATEGY:
            - Provide a joke/fact/game immediately in the text.
            - If they say 'I feel better' or 'Change topic', EXIT distraction mode. Ask what's next and set 'ui_mode' to 'text_input'.
            
            TONE RULES:
            - Communicate in {$languageName}.
            
            CONTEXT:
            Recent Conversation: {$recentMessages}
            
            JSON OUTPUT FORMAT:
            {
                \"ai_message\": \"<reply>\",
                \"ui_mode\": \"<buttons or text_input>\",
                \"options\": [{\"id\": \"opt_1\", \"label\": \"Short Label\"}]
            }
        ";

        $userInstruction = "User's message: " . $userMessageContent;
        return $this->executeAiRoute($session, $systemPrompt, $userInstruction);
    }

    private function handleVentingRoute($session, $user, $inputType, $userMessageContent)
    {
        $recentMessages = $this->repo->getRecentMessages($session->id);
        $recentMoods = $this->repo->getRecentMoods($user->id);
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = "
            You are Mann Mitra.
            
            CBT CONTINUATION RULE (CRITICAL OVERRIDE):
            If the Recent Conversation shows you are currently in the middle of a guided exercise (like Breathing, Reframing, or Grounding), ignore standard chat rules. Provide the next step. 
            - For Breathing: Instruct their breath and MUST set 'ui_mode' to 'breathing_animation' with an option {\"id\": \"next\", \"label\": \"Next\"}.
            - For Others: Ask the next step and set 'ui_mode' to 'text_input'.

            CRITICAL CBT SUGGESTION RULE (ANXIETY/PANIC):
            If user shows severe anxiety, offer grounding. Set 'ui_mode' to 'buttons'. Options: [{\"id\": \"start_cbt_grounding_01\", \"label\": \"Yes, let's start\"}, {\"id\": \"continue_chat\", \"label\": \"No, just talk\"}]

            CBT CONCLUSION RULE (END OF EXERCISE):
            If an exercise successfully finishes, praise them and STRICTLY set 'ui_mode' to 'emoji_slider'.
            
            TONE RULES:
            - Communicate in {$languageName}.
            - Be deeply validating ('I hear you'). No toxic positivity.

            UI WIDGET DECISION ENGINE:
            - 'breathing_animation' (only if continuing Breathing)
            - 'emoji_slider' (only if concluding CBT)
            - 'text_input' (for open questions)
            - 'voice_record' (if asked)
            - 'buttons' (quick choices)

            CONTEXT:
            Past 7 Days Moods: {$recentMoods}
            Recent Conversation: {$recentMessages}
            
            JSON OUTPUT FORMAT:
            {
                \"ai_message\": \"<reply>\",
                \"ui_mode\": \"<buttons, text_input, voice_record, emoji_slider, or breathing_animation>\",
                \"options\": [{\"id\": \"opt_1\", \"label\": \"Short Label\"}]
            }
        ";

        $userInstruction = ($inputType === 'init')
            ? "SYSTEM INSTRUCTION: The user just opened the app. Acknowledge they were feeling down recently and ask how they feel right now."
            : "User's message: " . $userMessageContent;

        return $this->executeAiRoute($session, $systemPrompt, $userInstruction);
    }

    private function executeAiRoute($session, $systemPrompt, $userInstruction)
    {
        try {
            $aiData = $this->openAi->getChatCompletion($systemPrompt, $userInstruction);

            $aiMessage = $aiData['ai_message'] ?? 'I am here for you.';
            $ui_mode = $aiData['ui_mode'] ?? 'text_input';
            $options = $aiData['options'] ?? [];

            $this->repo->createMessage($session->id, 'ai', 'text', $aiMessage);

            return [
                'node_id' => 'msg_' . time(),
                'ai_message' => $aiMessage,
                'ui_mode' => $ui_mode,
                'options' => $options
            ];
        } catch (\Exception $e) {
            Log::error("AI Route Error: " . $e->getMessage());
            return [
                'node_id' => 'msg_error_fallback',
                'ai_message' => 'I am here with you, but my connection is a bit slow right now. You can keep typing if you want.',
                'ui_mode' => 'text_input',
                'options' => []
            ];
        }
    }
}