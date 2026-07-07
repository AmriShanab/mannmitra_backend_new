<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\WhatsappMessage;

class WhatsAppController extends Controller
{
    public function handleWebhook(Request $request)
    {
        Log::info('1. Whapi Webhook Received');

        $messages = $request->input('messages', []);
        if (empty($messages)) {
            return response()->json(['status' => 'ignored', 'reason' => 'No messages found']);
        }

        $message = $messages[0];

        if (isset($message['from_me']) && $message['from_me'] === true) {
            return response()->json(['status' => 'ignored', 'reason' => 'Message is from bot']);
        }

        $senderId = $message['chat_id'] ?? $message['from'] ?? null;

        $type = $message['type'] ?? 'text';
        $userText = null;
        $isVoice = false;

        if ($type === 'text') {
            $userText = $message['text']['body'] ?? null;
        } elseif ($type === 'audio' || $type === 'voice') {
            $isVoice = true;
            $audioObj = $message['audio'] ?? $message['voice'] ?? null;
            if ($audioObj) {
                Log::info("Audio object found, downloading and transcribing...");
                $userText = $this->transcribeAudio($audioObj);
            }
        }

        if (!$userText) {
            return response()->json(['status' => 'ignored', 'reason' => 'Not a text or recognized audio message']);
        }

        $cleanNumber = explode('@', $senderId)[0];

        WhatsappMessage::create([
            'phone_number' => $cleanNumber,
            'role' => 'user',
            'content' => $userText
        ]);

        Log::info('2. Saved User Message & Sending to OpenAI...');

        $aiResponseText = $this->getOpenAiResponse($cleanNumber);

        Log::info('3. OpenAI replied: ' . $aiResponseText);

        WhatsappMessage::create([
            'phone_number' => $cleanNumber,
            'role' => 'assistant',
            'content' => $aiResponseText
        ]);

        if ($isVoice) {
            $audioContent = $this->generateVoice($aiResponseText);
            if ($audioContent) {
                $this->sendWhatsAppVoiceMessage($senderId, $audioContent);
            } else {
                $this->sendWhatsAppMessage($senderId, $aiResponseText);
            }
        } else {
            $this->sendWhatsAppMessage($senderId, $aiResponseText);
        }

        return response()->json(['status' => 'success']);
    }

    private function transcribeAudio($audioObj)
    {
        $fileContent = null;

        if (isset($audioObj['link'])) {
            $response = Http::get($audioObj['link']);
            if ($response->successful()) {
                $fileContent = $response->body();
            }
        } elseif (isset($audioObj['id'])) {
            $whapiUrl = env('WHAPI_URL');
            $whapiToken = env('WHAPI_TOKEN');
            $baseUrl = preg_replace('#/v\d+/?$#', '', $whapiUrl);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $whapiToken,
            ])->get("{$baseUrl}/media/{$audioObj['id']}");

            if ($response->successful()) {
                $fileContent = $response->body();
            }
        }

        if (!$fileContent) {
            Log::error('Could not download audio from Whapi.');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY')
            ])
                ->attach('file', $fileContent, 'audio.ogg')
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => 'whisper-1'
                ]);

            if ($response->successful()) {
                return $response->json('text');
            }

            Log::error('Whisper Transcription Error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Whisper Exception: ' . $e->getMessage());
            return null;
        }
    }

    private function generateVoice($text)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.openai.com/v1/audio/speech', [
                'model' => 'tts-1',
                'input' => $text,
                'voice' => 'alloy',
                'response_format' => 'mp3',
            ]);

            if ($response->successful()) {
                return $response->body();
            }
            Log::error('TTS Error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('TTS Exception: ' . $e->getMessage());
            return null;
        }
    }

    private function getOpenAiResponse($phoneNumber)
    {
        $systemPrompt = "You are Mann Mitra, a supportive, warm, and non-judgmental mental health companion and friend on WhatsApp. You exist EXCLUSIVELY to discuss emotions, mental health, and daily well-being.
        
        CORE RULES:
        1. MIRROR THE LANGUAGE: Detect the language and script the user is typing in and reply in that EXACT same language.
        2. WHATSAPP STYLE: Keep messages extremely concise (1-3 short sentences max). Use emojis naturally.
        3. THE PERSONA: Talk like a caring best friend, not a clinical doctor. Validate their feelings.
        4. BOUNDARIES (OFF-TOPIC): If the user asks for coding help (e.g., PHP, HTML), math, trivia, writing essays, or anything unrelated to mental health, politely and warmly decline. Remind them that you are here specifically for emotional support, and gently ask how they are feeling today. DO NOT fulfill the unrelated request.
        5. TEXT-BASED EXERCISES: If the user is anxious, gently offer a text-based exercise. (e.g., 'Look around and type out 3 things you can see.').
        6. NO APP UI: NEVER ask the user to 'click a button' or 'use the slider'. 
        7. CRISIS PROTOCOL: If the user expresses intent for self-harm, provide emergency contacts immediately.
        8. NO JSON: Output ONLY the raw conversational text.";

        $chatHistory = WhatsappMessage::where('phone_number', $phoneNumber)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->reverse();

        $openAiMessages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];


        foreach ($chatHistory as $msg) {
            $openAiMessages[] = [
                'role' => $msg->role,
                'content' => $msg->content
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => $openAiMessages
            ]);

            return $response->json('choices.0.message.content') ?? 'Sorry, my brain is offline right now!';
        } catch (\Exception $e) {
            Log::error('OpenAI Error: ' . $e->getMessage());
            return 'I am having trouble thinking right now. Please try again later.';
        }
    }

    private function sendWhatsAppMessage($to, $text)
    {
        $whapiUrl = env('WHAPI_URL');
        $whapiToken = env('WHAPI_TOKEN');

        Log::info("4. Sending back to Whapi... To: $to");

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $whapiToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post("{$whapiUrl}/messages/text", [
            'to' => $to,
            'body' => $text,
        ]);

        if ($response->successful()) {
            Log::info('5. SUCCESS! Message sent to WhatsApp.');
        } else {
            Log::error('5. WHAPI ERROR: ' . $response->body());
        }
    }

    private function sendWhatsAppVoiceMessage($to, $audioContent)
    {
        $whapiUrl = env('WHAPI_URL');
        $whapiToken = env('WHAPI_TOKEN');

        Log::info("4. Sending voice back to Whapi... To: $to");

        $base64 = base64_encode($audioContent);
        $media = 'data:audio/mp3;base64,' . $base64;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $whapiToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post("{$whapiUrl}/messages/audio", [
            'to' => $to,
            'media' => $media,
        ]);

        if ($response->successful()) {
            Log::info('5. SUCCESS! Voice Message sent to WhatsApp.');
        } else {
            Log::error('5. WHAPI ERROR (Voice): ' . $response->body());
        }
    }
}
