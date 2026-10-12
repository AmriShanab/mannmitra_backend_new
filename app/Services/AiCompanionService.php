<?php

namespace App\Services;

use App\Ai\AiBudgetService;
use App\Ai\AiResponseValidator;
use App\Ai\PromptRepository;
use App\Domain\Exercises\Exercise;
use App\Domain\Exercises\ExerciseSessionService;
use App\Enums\UserIntent;
use App\Models\User;
use App\Repositories\CompanionRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AiCompanionService
{
    protected $repo;
    protected $openAi;
    protected $crisisService;
    protected $exerciseAccess;
    protected $exercises;
    protected $validator;
    protected $prompts;
    protected $budget;

    private const INTENT_TO_ACTIVITY = [
        'cbt_breathing' => 'breathing_01',
        'cbt_grounding' => 'grounding_01',
        'cbt_reframing' => 'reframing_01',
    ];

    // Helpline numbers live in code so the model can never alter them.
    private const CRISIS_OPTIONS = [
        ['id' => '9152987821', 'label' => 'Call iCall'],
        ['id' => '9820466726', 'label' => 'Call AASRA'],
        ['id' => '18602662345', 'label' => 'Call Vandrevala'],
    ];

    public function __construct(CompanionRepository $repo, AIService $openAi, CrisisService $crisisService, ExerciseAccessService $exerciseAccess, ExerciseSessionService $exercises, AiResponseValidator $validator, PromptRepository $prompts, AiBudgetService $budget)
    {
        $this->budget = $budget;
        $this->exerciseAccess = $exerciseAccess;
        $this->exercises = $exercises;
        $this->validator = $validator;
        $this->prompts = $prompts;
        $this->repo = $repo;
        $this->openAi = $openAi;
        $this->crisisService = $crisisService;
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
                // Distress-level choices inside a managed exercise (e.g. reframing).
                if ((str_starts_with((string) $inputValue, 'rf_int_') || str_starts_with((string) $inputValue, 'sleep_') || str_starts_with((string) $inputValue, 'goal_'))
                    && ($managedRun = $this->exercises->activeManagedRun($user))) {
                    $this->repo->createMessage($session->id, 'user', 'text', '[User selected: ' . mb_substr((string) $inputValue, 0, 120) . ']');
                    return $this->exercises->advance($session, $user, $managedRun, Exercise::INPUT_CHOICE, $inputValue);
                }

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

                    $run = $this->exerciseAccess->ensureRun($user, $activityId);
                    if (!$run) {
                        return $this->paymentRequiredNode($session, $user, $activityId);
                    }

                    // Save message and GO DIRECTLY TO EXERCISE ROUTE!
                    $this->repo->createMessage($session->id, 'user', 'text', $userMessageContent);

                    if ($this->exercises->isManaged($activityId)) {
                        return $this->exercises->advance($session, $user, $run, Exercise::INPUT_NEXT);
                    }
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

        // 2.5 Safety net: keyword crisis check on every free-text input, even mid-exercise.
        if (in_array($inputType, ['text_input', 'voice_record'], true)
            && $this->crisisService->detectCrisis((string) $userMessageContent)) {
            return $this->handleCrisisRoute($session, $user, $inputType, $userMessageContent);
        }

        // 2.6 Managed exercises run on server-side state: no triage, no LLM routing.
        if ($inputType === 'emoji_slider') {
            $wrapUp = $this->exercises->wrapUpMood($session, $user, $inputValue);
            if ($wrapUp) {
                return $wrapUp;
            }
        }
        if (in_array($inputType, ['text_input', 'voice_record'], true)
            && ($managedRun = $this->exercises->activeManagedRun($user))) {
            return $this->exercises->advance($session, $user, $managedRun, Exercise::INPUT_TEXT, (string) $userMessageContent);
        }

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

        // 3.9 Daily AI budget: free chat turns are capped; the safety net above is not.
        if (!$this->budget->consume($user)) {
            return $this->budgetExhaustedNode($session, $user);
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
                // Exercises only run inside a paid/started run (or its wrap-up turn).
                $managedActivity = self::INTENT_TO_ACTIVITY[$intent] ?? '';
                if ($this->exercises->isManaged($managedActivity) || !$this->exerciseAccess->hasLiveRun($user)) {
                    return $this->handleVentingRoute($session, $user, $inputType, $userMessageContent);
                }
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

        $systemPrompt = $this->prompts->render('triage', ['recent_messages' => $recentMessages]);

        try {
            $response = $this->openAi->getUserIntentUsingMini($systemPrompt, "User's message: \"" . $inputValue . "\"");
            return $this->validator->intent($response, UserIntent::all()) ?? 'venting_sad';
        } catch (\Exception $e) {
            Log::warning('Intent triage failed: ' . $e->getMessage());
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
        if (!$this->exerciseAccess->ensureRun($user, $activityId)) {
            return $this->paymentRequiredNode($session, $user, $activityId);
        }

        if ($this->exercises->isManaged($activityId)) {
            return $this->exercises->start($session, $user, $activityId);
        }

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
        } else {
            Log::info("User is in Reframing Exercise. Recent Messages: " . $recentMessages);
            $systemPrompt = $this->prompts->render('reframing', [
                'language' => $languageName,
                'recent_messages' => $recentMessages,
            ]);
        }

        $result = $this->executeAiRoute($session, $systemPrompt, "User input: " . $userMessageContent, [], null, 'exercise.' . $activityId);

        // The prompts end every exercise with the mood slider; that is the "finished" signal.
        if (($result['ui_mode'] ?? null) === 'emoji_slider' && $result['node_id'] !== 'msg_error_fallback') {
            $run = $this->exerciseAccess->completeActiveRun($user);
            if ($run) {
                $result['exercise_completed'] = true;
                $result['sessions_left'] = $run->free_pass ? null : $this->exerciseAccess->sessionsLeft($user, $run->activity_id);
            }
        }

        return $result;
    }

    private function budgetExhaustedNode($session, $user)
    {
        $message = ($user->language === 'hi')
            ? "Aaj ke liye hamari baat ki limit poori ho gayi hai, kal phir milte hain. Aap abhi bhi mood log kar sakte hain ya journal likh sakte hain. Agar aap mushkil mein hain, to iCall (9152987821) ya AASRA (9820466726) par abhi call kijiye."
            : "We've reached today's chat limit, and I'll be back tomorrow. You can still log your mood or write in your journal. If you're struggling right now, please call iCall (9152987821) or AASRA (9820466726).";

        $this->repo->createMessage($session->id, 'ai', 'text', $message);

        return [
            'node_id' => 'budget_exhausted',
            'ai_message' => $message,
            'ui_mode' => 'text_input',
            'options' => [],
        ];
    }

    private function paymentRequiredNode($session, $user, $activityId)
    {
        $price = $this->exerciseAccess->pricing();
        $message = ($user->language === 'hi')
            ? "Is exercise ke {$price['sessions']} sessions unlock karne ke liye ₹{$price['amount']} ka payment karein."
            : "Unlock {$price['sessions']} sessions of this exercise for ₹{$price['amount']} to continue.";

        $this->repo->createMessage($session->id, 'ai', 'text', $message);

        return [
            'node_id' => 'payment_required',
            'ai_message' => $message,
            'ui_mode' => 'payment_required',
            'options' => [],
            'payment' => [
                'activity_id' => $activityId,
                'price' => $price['amount'],
                'pack_sessions' => $price['sessions'],
            ],
        ];
    }

    // =========================================================================
    // STANDARD CHAT ROUTES
    // =========================================================================

    private function handleCrisisRoute($session, $user, $inputType, $userMessageContent)
    {
        $this->repo->flagLatestMessageAsCrisis($session->id);
        $this->repo->createCrisisAlert($session->id, 'AI Triage Detected Crisis');
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = $this->prompts->render('crisis', ['language' => $languageName]);
        return $this->executeAiRoute(
            $session,
            $systemPrompt,
            "User's message: " . $userMessageContent,
            ['ui_mode' => 'crisis_cards', 'options' => self::CRISIS_OPTIONS],
            ($user->language === 'hi')
                ? 'Aap akele nahi hain. Please abhi in helplines par baat kijiye, main yahin hoon.'
                : "You're not alone. Please reach out to one of these helplines right now. I'm here with you.",
            'companion.crisis'
        );
    }

    private function handleHappyRoute($session, $user, $inputType, $userMessageContent)
    {
        $recentMessages = $this->repo->getRecentMessages($session->id);
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = $this->prompts->render('happy', ['language' => $languageName, 'recent_messages' => $recentMessages]);

        $userInstruction = ($inputType === 'init') ? "SYSTEM INSTRUCTION: The user just opened the app. Greet them warmly." : "User's message: " . $userMessageContent;
        return $this->executeAiRoute($session, $systemPrompt, $userInstruction);
    }

    private function handleJournalingRoute($session, $user, $inputType, $userMessageContent)
    {
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';
        $systemPrompt = $this->prompts->render('journaling', ['language' => $languageName]);

        return $this->executeAiRoute($session, $systemPrompt, "User's message: " . $userMessageContent);
    }

    private function handleDistractionRoute($session, $user, $inputType, $userMessageContent)
    {
        $recentMessages = $this->repo->getRecentMessages($session->id);
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = $this->prompts->render('distraction', ['recent_messages' => $recentMessages]);

        return $this->executeAiRoute($session, $systemPrompt, "User's message: " . $userMessageContent);
    }

    private function handleVentingRoute($session, $user, $inputType, $userMessageContent)
    {
        $recentMessages = $this->repo->getRecentMessages($session->id);
        $recentMoods = $this->repo->getRecentMoods($user->id);
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = $this->prompts->render('venting', ['language' => $languageName, 'recent_moods' => $recentMoods, 'recent_messages' => $recentMessages]);

        $userInstruction = ($inputType === 'init') ? "SYSTEM INSTRUCTION: The user just opened the app. Acknowledge they were feeling down recently and ask how they feel right now." : "User's message: " . $userMessageContent;
        return $this->executeAiRoute($session, $systemPrompt, $userInstruction);
    }

    private function executeAiRoute($session, $systemPrompt, $userInstruction, array $override = [], ?string $fallbackMessage = null, string $feature = 'companion.chat')
    {
        try {
            $aiData = $this->validator->node($this->openAi->getChatCompletion($systemPrompt, $userInstruction, $feature));

            $aiMessage = $aiData['ai_message'];
            $ui_mode = $override['ui_mode'] ?? $aiData['ui_mode'];
            $options = $override['options'] ?? $aiData['options'];

            $this->repo->createMessage($session->id, 'ai', 'text', $aiMessage);

            return [
                'node_id' => 'msg_' . time(),
                'ai_message' => $aiMessage,
                'ui_mode' => $ui_mode,
                'options' => $options
            ];
        } catch (\Exception $e) {
            Log::error("AI Route Error: " . $e->getMessage());
            $message = $fallbackMessage ?? 'I am here with you, but my connection is a bit slow right now. You can keep typing if you want.';
            $this->repo->createMessage($session->id, 'ai', 'text', $message);
            return [
                'node_id' => 'msg_error_fallback',
                'ai_message' => $message,
                'ui_mode' => $override['ui_mode'] ?? 'text_input',
                'options' => $override['options'] ?? []
            ];
        }
    }
}
