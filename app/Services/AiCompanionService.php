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
                // THE CBT INTERCEPTOR
                if (str_starts_with($inputValue, 'start_cbt_')) {
                    $cbtId = str_replace('start_cbt_', '', $inputValue);
                    $inputType = 'init_cbt';
                    $inputValue = $cbtId;
                    $userMessageContent = "[User accepted AI suggestion to start CBT Exercise: $cbtId]";
                }
                // NEW: Catch "I'm ready" or "Next" and bypass triage entirely!
                elseif (str_starts_with($inputValue, 'cbt_ready_')) {
                    $activityId = str_replace('cbt_ready_', '', $inputValue);
                    $userMessageContent = "[User clicked: I'm ready / Next Step]";

                    // Save message and GO DIRECTLY TO EXERCISE ROUTE!
                    $this->repo->createMessage($session->id, 'user', 'text', $userMessageContent);
                    return $this->handleCbtExerciseRoute($session, $user, $activityId, $userMessageContent);
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

        $recentMessages = $this->repo->getRecentMessages($session->id);

        // 4. The Triage Step
        $intent = ($inputType === 'init')
            ? \App\Enums\UserIntent::HAPPY_CASUAL
            : $this->classifyUserIntent($inputValue, $user->language, $recentMessages);

        // 5. The Router
        switch ($intent) {
            // Route Text-based exercises (Reframing/Grounding) to the unified engine
            case 'cbt_breathing':
            case 'cbt_grounding':
            case 'cbt_reframing':
                return $this->handleCbtExerciseRoute($session, $user, $intent, $userMessageContent);

            case 'crisis':
                return $this->handleCrisisRoute($session, $user, $inputType, $userMessageContent);
            case 'happy_casual':
                return $this->handleHappyRoute($session, $user, $inputType, $userMessageContent);
            case 'journaling':
                return $this->handleJournalingRoute($session, $user, $inputType, $userMessageContent);
            case 'needs_distraction':
                return $this->handleDistractionRoute($session, $user, $inputType, $userMessageContent);
            case 'venting_sad':
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
        return ['node_id' => 'msg_welcome_1', 'ai_message' => $welcomeText, 'ui_mode' => 'emoji_slider', 'options' => []];
    }

    private function classifyUserIntent($inputValue, $userLanguage, $recentMessages)
    {
        if (empty(trim($inputValue))) return 'happy_casual';

        $languageName = ($userLanguage === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = "
            You are an ultra-fast triage routing assistant for a mental health app.
            Analyze the user's latest message AND the Recent Conversation to classify their intent.
            
            CATEGORIES:
            - 'cbt_breathing': The user is currently participating in the Breathing exercise.
            - 'cbt_grounding': The user is currently participating in a Grounding exercise (e.g. 5-4-3-2-1).
            - 'cbt_reframing': The user is currently answering questions for a Cognitive Reframing exercise.
            - 'crisis': Self-harm, extreme panic, or immediate danger.
            - 'venting_sad': Crying, stressed, complaining, anxious, lonely.
            - 'happy_casual': Good news, casual greetings, normal non-stressful chat.
            - 'journaling': Explicitly wants to save a diary entry.
            - 'needs_distraction': Bored, asking for a game, or wanting to change the subject.

            RULES:
            1. Output STRICTLY in JSON format.
            2. If the recent conversation shows an active, unfinished exercise, you MUST return the corresponding 'cbt_' category.

            CONTEXT:
            Recent Conversation: {$recentMessages}

            EXPECTED OUTPUT FORMAT:
            {
                \"intent\": \"<one_of_the_categories_above>\"
            }
        ";

        try {
            $response = $this->openAi->getUserIntentUsingMini($systemPrompt, "User's message: \"" . $inputValue . "\"");
            if (isset($response['intent'])) return $response['intent'];
            return 'venting_sad';
        } catch (\Exception $e) {
            return 'venting_sad';
        }
    }

    private function handleDailyMoodCheckin($session, $user)
    {
        $welcomeText = "Hi {$user->name}! Welcome back. How are you feeling today?";
        if ($user->language === 'hi') {
            $welcomeText = "Namaste {$user->name}! Aaj aap kaisa mehsoos kar rahe hain?";
        }
        $this->repo->createMessage($session->id, 'ai', 'text', $welcomeText);
        return ['node_id' => 'msg_daily_mood_' . time(), 'ai_message' => $welcomeText, 'ui_mode' => 'emoji_slider', 'options' => []];
    }

    // =========================================================================
    // PHASE 3: CBT ENGINE (INITIALIZATION & DEDICATED CONDUCTOR)
    // =========================================================================

    private function handleCbtInitRoute($session, $user, $activityId)
    {
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        if ($activityId === 'breathing_01') {
            $aiMessage = "Welcome to the Breathing Exercise. Let's begin by taking a deep breath in...";
            if ($user->language === 'hi') {
                $aiMessage = "Swagat hai. Chaliye ek gehri saans lene se shuru karte hain...";
            }
            $uiMode = 'breathing_animation';
            // NEW: The button ID triggers the bypass!
            $options = [['id' => 'cbt_ready_breathing_01', 'label' => "I'm ready"]];
        } elseif ($activityId === 'grounding_01') {
            $aiMessage = "Let's do a quick grounding exercise together. Look around the room and type out 3 things you can see right now.";
            if ($user->language === 'hi') {
                $aiMessage = "Chaliye grounding exercise karte hain. Apne aas-paas dekhiye aur 3 cheezein bataiye jo aap dekh sakte hain.";
            }
            $uiMode = 'text_input';
            $options = [];
        } else {
            $aiMessage = "Welcome to Reframing. Let's work through your thoughts together. What is a negative thought you've been having recently?";
            if ($user->language === 'hi') {
                $aiMessage = "Chaliye aapki pareshaniyo par baat karte hain. Aapke dimaag mein kya chal raha hai?";
            }
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

    private function handleCbtExerciseRoute($session, $user, $activityId, $userMessageContent)
    {
        // This is the new centralized AI Engine that conducts the exercises!
        $recentMessages = $this->repo->getRecentMessages($session->id);
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        if ($activityId === 'breathing_01' || $activityId === 'cbt_breathing') {
            Log::info("User is in Breathing Exercise. Recent Messages: " . $recentMessages);
            $systemPrompt = "
                You are Mann Mitra, a supportive, warm, and caring friend. You are sitting beside the user, gently guiding them through a calming Breathing Exercise. DO NOT sound like a clinical therapist, a doctor, or a robot. Keep it very conversational and soothing.
                
                CRITICAL ANTI-LOOP & UI RULES:
                1. COUNT THE BREATHS: Look closely at the Recent Conversation. Count how many times you have already told them to inhale/exhale. 
                2. CYCLE LIMIT: If you see you have already guided them through 3 OR MORE breath cycles, the exercise is OVER. Say warmly: 'You did so well. I hope you feel a little lighter now. How are you feeling?' -> STRICTLY set 'ui_mode' to 'emoji_slider' with an empty array [].
                3. CONTINUE THE BREATH: If under 3 cycles, guide the next breath. Be highly varied and natural. Examples: 'Alright, let's take another slow breath in...', 'You're doing great, let's fill those lungs again...', 'One more deep breath, let it all go...'
                4. CONTINUE UI: If continuing the breath, ALWAYS set 'ui_mode' to 'breathing_animation' and provide: [{\"id\": \"cbt_ready_breathing_01\", \"label\": \"Next\"}].
                5. DOUBLE SLIDER PREVENTION: If the user just submitted a mood score (e.g., 'User Indicated a mood score...'), the exercise is completely finished. Acknowledge their mood like a good friend, ask what's on their mind, and set 'ui_mode' to 'text_input'.
                
                LANGUAGE: Communicate in {$languageName}.
                
                CONTEXT: {$recentMessages}
                
                JSON OUTPUT FORMAT:
                {
                    \"ai_message\": \"<your varied instruction, conclusion, or post-score acknowledgement>\",
                    \"ui_mode\": \"<breathing_animation, emoji_slider, or text_input>\",
                    \"options\": <array of buttons or empty array>
                }
            ";
        } elseif ($activityId === 'grounding_01' || $activityId === 'cbt_grounding') {
            Log::info("User is in Grounding Exercise. Recent Messages: " . $recentMessages);
            $systemPrompt = "
                You are Mann Mitra, a supportive, warm, and caring friend. You are helping the user get out of their head using the 5-4-3-2-1 Grounding technique. DO NOT sound clinical or like a textbook.
                
                CRITICAL ANTI-LOOP & UI RULES:
                1. CONVERSATIONAL REACTION: Look at the items they just typed. React to them naturally like a friend! (e.g., if they mention a coffee mug, say 'A warm mug of coffee is the best.', if they hear birds, say 'Birdsong is so peaceful.').
                2. TRACK THE SEQUENCE: Look at the Recent Conversation to see where you are. The strict order is: 5 things they SEE -> 4 things they FEEL -> 3 things they HEAR -> 2 things they SMELL -> 1 thing they TASTE. 
                3. ASK FOR THE NEXT SENSE: Based on the history, gently ask for the next step in the sequence. NEVER ask for the same sense twice.
                4. ALWAYS set 'ui_mode' to 'text_input' during the exercise.
                5. ENDING (1 TASTE): When they give you the final 1 thing they can taste, the exercise is OVER. Praise them warmly, say 'I'm so proud of you for doing this. How are you feeling right now?', and STRICTLY set 'ui_mode' to 'emoji_slider' with [].
                6. DOUBLE SLIDER PREVENTION: If the user just submitted a mood score (e.g., 'User Indicated a mood score...'), the exercise is OVER. Acknowledge their feeling warmly, ask what they want to talk about next, and STRICTLY set 'ui_mode' to 'text_input'.
                
                LANGUAGE: Communicate in {$languageName}.
                
                CONTEXT: {$recentMessages}
                
                JSON OUTPUT FORMAT:
                {
                    \"ai_message\": \"<your friendly reaction + next instruction>\",
                    \"ui_mode\": \"<text_input or emoji_slider>\",
                    \"options\": []
                }
            ";
         } // else {
        //     Log::info("User is in Reframing Exercise. Recent Messages: " . $recentMessages);
        //     $systemPrompt = "
        //         You are Mann Mitra, a warm, caring friend helping the user reframe a negative thought. DO NOT sound like a clinical therapist or use overly academic psychology terms.
                
        //         CRITICAL ANTI-LOOP & UI RULES:
        //         1. Read their last message. Acknowledge it gently and empathetically.
        //         2. Guide them like a friend: What's the thought? -> Is there proof against it? -> How can we look at this more fairly? DO NOT repeat the previous step.
        //         3. ALWAYS set 'ui_mode' to 'text_input' during the exercise.
        //         4. ENDING: Once they create a fairer, balanced thought, validate them warmly, ask how they are feeling now, and STRICTLY set 'ui_mode' to 'emoji_slider' with [].
        //         5. DOUBLE SLIDER PREVENTION: If the user just submitted a mood score (e.g., 'User Indicated a mood score...'), the exercise is OVER. Acknowledge their feeling warmly, ask what they want to talk about next, and STRICTLY set 'ui_mode' to 'text_input' (NEVER use emoji_slider again here).
                
        //         LANGUAGE: Communicate in {$languageName}.
                
        //         CONTEXT: {$recentMessages}
                
        //         JSON OUTPUT FORMAT:
        //         {
        //             \"ai_message\": \"<your friendly instruction or post-score acknowledgement>\",
        //             \"ui_mode\": \"<text_input or emoji_slider>\",
        //             \"options\": []
        //         }
        //     ";
        // }

        return $this->executeAiRoute($session, $systemPrompt, "User input: " . $userMessageContent);
    }

    // =========================================================================
    // STANDARD CHAT ROUTES
    // =========================================================================

    private function handleCrisisRoute($session, $user, $inputType, $userMessageContent)
    {
        $this->repo->flagLatestMessageAsCrisis($session->id);
        $this->repo->createCrisisAlert($session->id, 'AI Triage Detected Crisis');
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = "
            You are Mann Mitra, a crisis-intervention companion. The user is in acute distress.
            TONE RULES: Communicate in {$languageName}. Be gentle, grounding, and safe. Max 2 short sentences.
            UI WIDGET DECISION: MUST set 'ui_mode' to 'crisis_cards'.
            
            JSON OUTPUT FORMAT:
            {
                \"ai_message\": \"<message>\",
                \"ui_mode\": \"crisis_cards\",
                \"options\": [{\"id\": \"9152987821\", \"label\": \"Call iCall\"}, {\"id\": \"9820466726\", \"label\": \"Call AASRA\"}, {\"id\": \"18602662345\", \"label\": \"Call Vandrevala\"}]
            }
        ";
        return $this->executeAiRoute($session, $systemPrompt, "User's message: " . $userMessageContent);
    }

    private function handleHappyRoute($session, $user, $inputType, $userMessageContent)
    {
        $recentMessages = $this->repo->getRecentMessages($session->id);
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = "
            You are Mann Mitra.
            TONE & EXIT STRATEGY: Communicate in {$languageName}. If they just said 'I feel better', validate it warmly. Pivot back to a normal conversation with a grounded question.
            UI WIDGET DECISION: Use 'voice_record' if asked. Use 'text_input' for open-ended questions. Use 'buttons' for quick choices.
            CONTEXT: {$recentMessages}
            
            JSON OUTPUT FORMAT:
            {
                \"ai_message\": \"<reply>\",
                \"ui_mode\": \"<buttons, text_input, or voice_record>\",
                \"options\": [{\"id\": \"opt_1\", \"label\": \"Short Label\"}]
            }
        ";

        $userInstruction = ($inputType === 'init') ? "SYSTEM INSTRUCTION: The user just opened the app. Greet them warmly." : "User's message: " . $userMessageContent;
        return $this->executeAiRoute($session, $systemPrompt, $userInstruction);
    }

    private function handleJournalingRoute($session, $user, $inputType, $userMessageContent)
    {
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';
        $systemPrompt = "
            You are Mann Mitra. The user wants to journal.
            TONE RULES: Communicate in {$languageName}. Be supportive and reflective.
            UI WIDGET DECISION: Set 'ui_mode' to 'text_input'.
            
            JSON OUTPUT FORMAT:
            { \"ai_message\": \"<short prompt>\", \"ui_mode\": \"text_input\", \"options\": [] }
        ";
        return $this->executeAiRoute($session, $systemPrompt, "User's message: " . $userMessageContent);
    }

    private function handleDistractionRoute($session, $user, $inputType, $userMessageContent)
    {
        $recentMessages = $this->repo->getRecentMessages($session->id);
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = "
            You are Mann Mitra. The user wants a distraction.
            CRITICAL ANTI-LOOP: Provide a joke/fact/game immediately. If they say 'I feel better', EXIT distraction mode. Ask what's next and set 'ui_mode' to 'text_input'.
            CONTEXT: {$recentMessages}
            
            JSON OUTPUT FORMAT:
            { \"ai_message\": \"<reply>\", \"ui_mode\": \"<buttons or text_input>\", \"options\": [{\"id\": \"opt_1\", \"label\": \"Short Label\"}] }
        ";
        return $this->executeAiRoute($session, $systemPrompt, "User's message: " . $userMessageContent);
    }

    private function handleVentingRoute($session, $user, $inputType, $userMessageContent)
    {
        $recentMessages = $this->repo->getRecentMessages($session->id);
        $recentMoods = $this->repo->getRecentMoods($user->id);
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = "
            You are Mann Mitra.
            CRITICAL CBT SUGGESTION RULE: If user shows severe anxiety, offer grounding. Set 'ui_mode' to 'buttons'. Options: [{\"id\": \"start_cbt_grounding_01\", \"label\": \"Yes, let's start\"}, {\"id\": \"continue_chat\", \"label\": \"No, just talk\"}]
            TONE RULES: Communicate in {$languageName}. Be deeply validating ('I hear you'). No toxic positivity.
            CONTEXT: Past 7 Days Moods: {$recentMoods} | Recent Conversation: {$recentMessages}
            
            JSON OUTPUT FORMAT:
            { \"ai_message\": \"<reply>\", \"ui_mode\": \"<buttons, text_input, or voice_record>\", \"options\": [{\"id\": \"opt_1\", \"label\": \"Short Label\"}] }
        ";

        $userInstruction = ($inputType === 'init') ? "SYSTEM INSTRUCTION: The user just opened the app. Acknowledge they were feeling down recently and ask how they feel right now." : "User's message: " . $userMessageContent;
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
