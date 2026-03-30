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
            case 'text_input':
                $userMessageContent = $inputValue;
                break;

            case 'init':
                $userMessageContent = "[User opened the app and started the session]";
                break;
        }

        // 2. Save User Message to DB (So it appears in recent messages context)
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

        if ($inputType === 'init' && !$hasMoodToday) {
            return $this->handleDailyMoodCheckin($session, $user);
        }

        // 4. The Triage Step: Classify emotional intent
        $intent = ($inputType === 'init')
            ? \App\Enums\UserIntent::HAPPY_CASUAL
            : $this->classifyUserIntent($inputValue, $user->language);

        // 5. The Router: Send them to the specialized Therapist Prompt
        switch ($intent) {
            case UserIntent::CRISIS:
                return $this->handleCrisisRoute($session, $user, $inputType, $savedUserMessage?->id);

            case UserIntent::HAPPY_CASUAL:
                return $this->handleHappyRoute($session, $user, $inputType, $savedUserMessage?->id);

            case UserIntent::JOURNALING:
                return $this->handleJournalingRoute($session, $user, $inputType, $savedUserMessage?->id);

            case UserIntent::NEEDS_DISTRACTION:
                return $this->handleDistractionRoute($session, $user, $inputType, $savedUserMessage?->id);

            case UserIntent::VENTING_SAD:
            default:
                return $this->handleVentingRoute($session, $user, $inputType, $savedUserMessage?->id);
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
            - 'happy_casual': Good news, casual greetings (Hi, Good morning), normal non-stressful chat.
            - 'journaling': Explicitly wants to save a diary entry, record a memory, or log a note.
            - 'needs_distraction': Bored, asking for a game, wanting to change the subject, or overthinking.

            RULES:
            1. Output STRICTLY in JSON format.
            2. Do NOT include any other text, markdown formatting, or explanations.
            3. The language of the user's input will be in {$languageName}.

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

    private function handleCrisisRoute($session, $user, $inputType, $currentMessageId)
    {
        // 1. Instantly log the crisis to alert human Listeners/Doctors
        $this->repo->flagLatestMessageAsCrisis($session->id);
        $this->repo->createCrisisAlert($session->id, 'AI Triage Detected Crisis');

        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = "
            You are Mann Mitra, a crisis-intervention companion. 
            The user is in acute distress, expressing hopelessness, or in danger.
            
            TONE RULES:
            - Communicate entirely in {$languageName}.
            - Be extremely gentle, grounding, and safe.
            - Remind them they are not alone, they are brave for sharing, and that help is available.
            - Keep it to a maximum of 2 short, calming sentences. Do not overwhelm them with text.
            
            UI WIDGET DECISION ENGINE (CRITICAL OVERRIDE):
            - You MUST set 'ui_mode' to 'crisis_cards'.
            - Provide these exact 3 emergency options in the 'options' array.
            
            JSON OUTPUT FORMAT (STRICT):
            {
                \"ai_message\": \"<your grounding, safe message>\",
                \"ui_mode\": \"crisis_cards\",
                \"options\": [
                    {\"id\": \"9152987821\", \"label\": \"Call iCall\"},
                    {\"id\": \"9820466726\", \"label\": \"Call AASRA\"},
                    {\"id\": \"18602662345\", \"label\": \"Call Vandrevala\"}
                ]
            }
        ";

        $userInstruction = "User's latest input type: " . $inputType;
        return $this->executeAiRoute($session, $systemPrompt, $userInstruction);
    }

    private function handleHappyRoute($session, $user, $inputType, $currentMessageId)
    {
        $recentMessages = $this->repo->getRecentMessages($session->id);
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = "
            You are Mann Mitra, a warm and engaging friend. 
            The user is in a good mood, sharing casual news, or just indicated they feel better after being sad/distracted.
            
            TONE & EXIT STRATEGY:
            - Communicate entirely in {$languageName}.
            - If the user just said 'I feel better' or 'Change the topic' after a previous distraction, warmly validate their progress (e.g., 'I am so happy to hear that!').
            - Gently pivot back to a normal conversation by asking a grounded, open-ended question (e.g., 'What's on your mind now?' or 'How do you want to spend the rest of your day?').
            - Do not bring up heavy emotional topics unless they do.

            UI WIDGET DECISION ENGINE (DYNAMIC UI):
            - If they explicitly ask to use voice, set 'ui_mode' to 'voice_record'.
            - If you are asking a grounded, open-ended question to transition the conversation (like 'What is on your mind now?'), you MUST set 'ui_mode' to 'text_input'.
            - Only use 'buttons' if you are making casual small talk and want to offer fun, quick choices.

            CONTEXT:
            Recent Conversation: {$recentMessages}
            
            JSON OUTPUT FORMAT (STRICT):
            {
                \"ai_message\": \"<your cheerful or grounding reply>\",
                \"ui_mode\": \"<buttons, text_input, or voice_record>\",
                \"options\": [{\"id\": \"opt_1\", \"label\": \"Short Label\"}]
            }
        ";

        $userInstruction = "User's latest input type: " . $inputType;
        return $this->executeAiRoute($session, $systemPrompt, $userInstruction);
    }

    private function handleJournalingRoute($session, $user, $inputType, $currentMessageId)
    {
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = "
            You are Mann Mitra. The user has explicitly stated they want to write a diary entry, journal, or record a thought.
            
            TONE RULES:
            - Communicate entirely in {$languageName}.
            - Be quiet, supportive, and reflective.
            - Simply tell them you are ready to listen and they can take their time.
            
            UI WIDGET DECISION ENGINE:
            - Because they want to journal, they need to type or speak. 
            - Set 'ui_mode' to 'text_input' so they can begin writing.
            - Do not use buttons here; give them the space to express themselves.
            
            JSON OUTPUT FORMAT (STRICT):
            {
                \"ai_message\": \"<short supportive prompt to start journaling>\",
                \"ui_mode\": \"text_input\",
                \"options\": []
            }
        ";

        $userInstruction = "User's latest input type: " . $inputType;
        return $this->executeAiRoute($session, $systemPrompt, $userInstruction);
    }

    private function handleDistractionRoute($session, $user, $inputType, $currentMessageId)
    {
        $recentMessages = $this->repo->getRecentMessages($session->id);
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = "
            You are Mann Mitra. The user wants a distraction to take their mind off things.
            
            CRITICAL ANTI-LOOP & EXIT STRATEGY:
            - If they asked for a 'joke', 'fact', or 'game', execute it immediately in your message!
            - IF THE USER SAYS 'I feel better', 'Change the topic', or 'Enough': You MUST EXIT distraction mode. Acknowledge their shift (e.g., 'I am so glad you are feeling a bit lighter!'), ask what they would like to focus on next, and strictly set 'ui_mode' to 'text_input'. Do NOT offer more games.
            
            TONE RULES:
            - Communicate entirely in {$languageName}.
            - Be lighthearted, engaging, and supportive.
            
            UI WIDGET DECISION ENGINE:
            - If you are playing a word game where they need to guess, strictly set 'ui_mode' to 'text_input'.
            - If you just told a joke or a fact, set 'ui_mode' to 'buttons' and offer follow-ups OR an exit (e.g., 'Another joke', 'I feel better', 'Change topic').
            - If they triggered the Exit Strategy ('I feel better'), you MUST use 'text_input'.
            
            CONTEXT:
            Recent Conversation: {$recentMessages}
            
            JSON OUTPUT FORMAT (STRICT):
            {
                \"ai_message\": \"<your reply>\",
                \"ui_mode\": \"<buttons or text_input>\",
                \"options\": [{\"id\": \"opt_1\", \"label\": \"Short Label\"}]
            }
        ";

        $userInstruction = "Please execute the distraction based on my latest message.";
        return $this->executeAiRoute($session, $systemPrompt, $userInstruction);
    }

    private function handleVentingRoute($session, $user, $inputType, $currentMessageId)
    {
        $recentMessages = $this->repo->getRecentMessages($session->id);
        $recentMoods = $this->repo->getRecentMoods($user->id);
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = "
            You are Mann Mitra, an incredibly empathetic, warm, and gentle friend. 
            The user is currently venting, stressed, sad, or overwhelmed.
            
            TONE RULES:
            - Communicate entirely in {$languageName}.
            - Be deeply validating. Say things like 'I hear you', 'That sounds so heavy', or 'It makes sense you feel that way'.
            - NEVER use 'toxic positivity' (e.g., Do NOT say 'Cheer up', 'Look on the bright side', or 'It will be okay!').
            - Keep your response to a maximum of 2 short sentences.

         UI WIDGET DECISION ENGINE (DYNAMIC UI):
            - If the user explicitly asks to speak, record audio, or use voice, set 'ui_mode' to 'voice_record'.
            - If you are asking a deep or open-ended question (e.g., 'What exactly happened?', 'How did that make you feel?'), you MUST set 'ui_mode' to 'text_input'.
            - If you are just offering comfort, a quick choice, or if the user seems too exhausted to type, set 'ui_mode' to 'buttons' (Max 2-4 options).
            - DO NOT use buttons for every single reply. Mix 'text_input' and 'buttons' so it feels like a natural human chat.\

            CONTEXT:
            Past 7 Days Moods: {$recentMoods}
            Recent Conversation: {$recentMessages}
            
            JSON OUTPUT FORMAT (STRICT):
            {
                \"ai_message\": \"<your gentle reply>\",
                \"ui_mode\": \"<buttons or text_input>\",
                \"options\": [{\"id\": \"opt_1\", \"label\": \"Short Label\"}]
            }
        ";

        $userInstruction = "User's latest input type: " . $inputType;
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
