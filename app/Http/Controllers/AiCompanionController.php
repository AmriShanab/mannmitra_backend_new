<?php

namespace App\Http\Controllers;

use App\Models\CrisisAlert;
use App\Models\JournalEntry;
use App\Models\Message;
use App\Models\MoodEntry;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiCompanionController extends Controller
{
    public function interact(Request $request)
    {
        dd(123);
        // 1. Validate incoming request
        $request->validate([
            'input_type' => 'required|string|in:init,buttons,emoji_slider,text_input,voice_record,crisis_contacted',
            'input_value' => 'nullable',
        ]);

        $user = Auth::user();
        $inputType = $request->input('input_type');
        $inputValue = $request->input('input_value');

        // 2. Find or Create Active Session
        $activeSession = Session::firstOrCreate(
            ['user_id' => $user->id, 'status' => 'active'],
            ['type' => 'text']
        );

        // 3. Process the Input (The "Traffic Cop")
        $userMessageContent = $inputValue;
        $audioPath = null;

        switch ($inputType) {
            case 'emoji_slider':
                MoodEntry::create([
                    'user_id' => $user->id,
                    'primary_mood' => $inputValue,
                    'note' => 'Logged via AI Companion - Emoji Slider',
                ]);
                $userMessageContent = "[User Indicated a mood score of : $inputValue/10]";
                break;

            case 'voice_record':
                if ($request->hasFile('input_value')) {
                    $audioFile = $request->file('input_value');
                    $audioPath = $audioFile->store('companion_audio', 'public');
                    $transcribedText = $this->transcribeAudio($audioFile, $user->language);

                    JournalEntry::create([
                        'user_id' => $user->id,
                        'content' => $transcribedText,
                        'audio_path' => $audioPath,
                    ]);
                    $userMessageContent = $transcribedText;
                } else {
                    $userMessageContent = "[Audio file Missing]";
                }
                break;

            case 'crisis_contacted':
                CrisisAlert::create([
                    'session_id' => $activeSession->id,
                    'trigger_keyword' => 'User manually clicked emergency contact',
                    'severity' => 'high',
                    'status' => 'pending'
                ]);
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

        // 4. Save User Message to Database
        if ($userMessageContent) {
            Message::create([
                'session_id' => $activeSession->id,
                'sender' => 'user',
                'type' => ($inputType === 'voice_record') ? 'audio' : 'text',
                'content' => $userMessageContent,
                'audio_path' => $audioPath,
            ]);
        }

        // 5. Day 1 Check (Onboarding)
        $totalMessagesCount = Message::whereHas('session', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->count();

        if ($totalMessagesCount <= 1 && $inputType === 'init') {
            $welcomeText = "Hi {$user->name}, I'm Mann Mitra. I'm here to listen, without any judgment. How are you feeling right now?";
            if ($user->language === 'hi') {
                $welcomeText = "Namaste {$user->name}, main Mann Mitra hoon. Main yahan sirf aapko sunne ke liye hoon, bina kisi judgment ke. Abhi aap kaisa mehsoos kar rahe hain?";
            }

            Message::create([
                'session_id' => $activeSession->id,
                'sender' => 'ai',
                'type' => 'text',
                'content' => $welcomeText,
            ]);

            return response()->json([
                'status' => true,
                'data' => [
                    'node_id' => 'msg_welcome_1',
                    'ai_message' => $welcomeText,
                    'ui_mode' => 'buttons', // Forced UI mode for Day 1
                    'options' => [
                        ['id' => 'feel_good', 'label' => 'Feeling good'],
                        ['id' => 'feel_anxious', 'label' => 'A bit anxious'],
                        ['id' => 'feel_low', 'label' => 'Feeling low'],
                        ['id' => 'just_exploring', 'label' => 'Just exploring']
                    ]
                ]
            ]);
        }

        // 6. Gather Context for OpenAI (Day 2+)
        $recentMessages = Message::where('session_id', $activeSession->id)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->reverse()
            ->map(function ($msg) {
                return ucfirst($msg->sender) . ": " . $msg->content;
            })->implode("\n");

        $recentMoods = MoodEntry::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($mood) {
                return "Date: {$mood->created_at->format('Y-m-d')}, Score: {$mood->primary_mood}/10";
            })->implode(" | ");
        if (empty($recentMoods)) $recentMoods = "No recent mood data.";

        $recentJournals = JournalEntry::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get()
            ->map(function ($journal) {
                return "Date: {$journal->created_at->format('Y-m-d')}, Content: {$journal->content}";
            })->implode(" | ");
        if (empty($recentJournals)) $recentJournals = "No recent journal entries.";

        // 7. Build System Prompt & Dynamic User Prompt
        $languageName = ($user->language === 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $systemPrompt = "
            You are Mann Mitra, a close, warm, and deeply empathetic human friend to the user. You are NOT an AI assistant, bot, or therapist. You do not give unsolicited advice. You listen, validate, and care.
            
            LANGUAGE & TONE RULE (CRITICAL):
            You MUST communicate entirely in $languageName. Act like a real friend texting them. Never say 'How can I assist you' or 'I am an AI'. Keep it casual, short (Max 2 sentences), and highly natural.
            
            CONTEXT:
            Past 7 Days Moods: $recentMoods
            Past Journals: $recentJournals
            
            YOUR TASK:
            1. Analyze the context and the user's latest input.
            2. Reply as a comforting friend.
            3. Decide the BEST UI widget based on their psychological state and energy level.
            
            UI WIDGET DECISION ENGINE (CRITICAL):
            Do not lazily default to 'text_input'. Match the UI friction to the user's current emotional bandwidth:
            - 'buttons': Use when the user seems overwhelmed, highly anxious, exhausted, or stuck. Reduce their cognitive load by giving them 2 to 4 easy clickable options (e.g., \"Listen to me vent\", \"Distract me\").
            - 'voice_record': Use when the user is frustrated, angry, or has a complex story. (e.g., \"If it's a lot to type, send me a voice note.\").
            - 'emoji_slider': Use if you haven't checked their mood yet today and want to establish a baseline.
            - 'text_input': Use when the user is calm or chatty.
            - 🚨 CRISIS RULE: If the user expresses extreme distress, self-harm, or suicide, stop normal chat. Set 'ui_mode' to 'crisis_cards'.
            
            YOU MUST RESPOND STRICTLY IN THIS JSON FORMAT:
            {
                \"ai_message\": \"<your conversational friend-like reply>\",
                \"ui_mode\": \"<must be: 'text_input', 'buttons', 'emoji_slider', 'voice_record', or 'crisis_cards'>\",
                \"options\": [{\"id\": \"opt_1\", \"label\": \"I need to vent\"}, {\"id\": \"opt_2\", \"label\": \"Distract me\"}] // Only if ui_mode is 'buttons' or 'crisis_cards'. Labels must be under 4 words.
            }
        ";

        // Secretly instruct the AI if the user just opened the app
        $userInstruction = "Recent Conversation:\n" . $recentMessages . "\n\nUser just triggered: " . $inputType;
        if ($inputType === 'init') {
            $userInstruction .= "\n\n[SECRET SYSTEM INSTRUCTION]: The user just opened the app. Greet them warmly like a friend. Check the CONTEXT. If they had a negative mood score or stressful journal recently, gently and naturally check in on that specific thing (e.g., 'Yesterday you seemed a bit worried about [topic], how are you feeling today?').";
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'Content-Type' => 'application/json',
            ])->timeout(15)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o',
                'response_format' => ['type' => 'json_object'], // FORCES JSON OUTPUT
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userInstruction]
                ],
                'temperature' => 0.7
            ]);

            if ($response->successful()) {
                $aiResponseString = $response->json('choices.0.message.content');
                $aiData = json_decode($aiResponseString, true);

                // --- Crisis Interceptor ---
                if (isset($aiData['ui_mode']) && $aiData['ui_mode'] === 'crisis_cards') {
                    Message::where('session_id', $activeSession->id)
                        ->orderBy('id', 'desc')
                        ->first()
                        ->update(['is_crisis' => true]);

                    CrisisAlert::create([
                        'session_id' => $activeSession->id,
                        'trigger_keyword' => 'AI Detected Crisis',
                        'severity' => 'high',
                        'status' => 'pending'
                    ]);
                }

                // Save AI's response
                Message::create([
                    'session_id' => $activeSession->id,
                    'sender' => 'ai',
                    'type' => 'text',
                    'content' => $aiData['ai_message'] ?? 'I am here for you.',
                ]);

                // Return final payload to Flutter
                return response()->json([
                    'status' => true,
                    'data' => [
                        'node_id' => 'msg_' . time(),
                        'ai_message' => $aiData['ai_message'] ?? 'I am here for you. Take a deep breath.',
                        'ui_mode' => $aiData['ui_mode'] ?? 'text_input',
                        'options' => $aiData['options'] ?? []
                    ]
                ]);
            }

            throw new \Exception("OpenAI API failed with status: " . $response->status());

        } catch (\Exception $e) {
            Log::error("AI Companion Error: " . $e->getMessage());

            // Fallback response if OpenAI fails
            return response()->json([
                'status' => true,
                'data' => [
                    'node_id' => 'msg_error_fallback',
                    'ai_message' => 'I am here with you, but my connection is a bit slow right now. You can keep typing if you want.',
                    'ui_mode' => 'text_input',
                    'options' => []
                ]
            ]);
        }
    }

    private function transcribeAudio($audioFile, $languageCode = 'en')
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
            ])->attach(
                'file',
                file_get_contents($audioFile->getRealPath()),
                $audioFile->getClientOriginalName()
            )->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'language' => $languageCode
            ]);

            if ($response->successful()) {
                return $response->json('text');
            }

            return "[Voice note unreadable]";
        } catch (\Exception $e) {
            Log::error("Whisper API Error: " . $e->getMessage());
            return "[Error processing voice note]";
        }
    }
}