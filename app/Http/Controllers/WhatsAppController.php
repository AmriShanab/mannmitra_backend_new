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
        $secret = (string) config('services.whapi.webhook_secret');
        if ($secret === '') {
            return response()->json(['status' => 'error', 'reason' => 'Webhook not configured'], 503);
        }
        $given = (string) ($request->query('token') ?? $request->header('X-Webhook-Token', ''));
        if (!hash_equals($secret, $given)) {
            return response()->json(['status' => 'error', 'reason' => 'Unauthorized'], 401);
        }

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

        // Do not log message bodies (private conversations).
        Log::info('3. OpenAI replied (' . mb_strlen((string) $aiResponseText) . ' chars)');

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

        // Media is fetched by id through the Whapi API only. A raw `link` from the payload is
        // fetched only if it points at the Whapi host, so the server can't be made to request
        // arbitrary (e.g. internal) URLs.
        $whapiHost = parse_url((string) config('services.whapi.url'), PHP_URL_HOST);
        $link = $audioObj['link'] ?? null;
        $linkAllowed = is_string($link) && $whapiHost
            && parse_url($link, PHP_URL_SCHEME) === 'https'
            && parse_url($link, PHP_URL_HOST) === $whapiHost;

        if (!isset($audioObj['id']) && $linkAllowed) {
            $response = Http::timeout(20)->get($link);
            if ($response->successful()) {
                $fileContent = $response->body();
            }
        } elseif (isset($audioObj['id'])) {
            $whapiUrl = config('services.whapi.url');
            $whapiToken = config('services.whapi.token');
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

        return app(\App\Ai\AiClient::class)->transcribe('whatsapp.transcribe', null, 'audio.ogg', 'en', $fileContent);
    }

    private function generateVoice($text)
    {
        return app(\App\Ai\AiClient::class)->speak('whatsapp.speak', $text);
    }

    private function getOpenAiResponse($phoneNumber)
    {
        $systemPrompt = "You are Mann Mitra, a supportive, warm, and non-judgmental mental health companion and friend on WhatsApp. You exist EXCLUSIVELY to discuss emotions, mental health, and daily well-being.
        
        CORE RULES:
        1. MIRROR THE LANGUAGE: Detect the language and script the user is typing in and reply in that EXACT same language.
        2. WHATSAPP STYLE: Keep messages extremely concise (1-3 short sentences, under 40 words total). Use emojis naturally.
        3. THE PERSONA: Talk like a caring best friend, not a clinical doctor. Validate their feelings.
        4. BOUNDARIES (OFF-TOPIC): If the user asks for coding help (e.g., PHP, HTML), math, trivia, writing essays, or anything unrelated to mental health, politely and warmly decline. Remind them that you are here specifically for emotional support, and gently ask how they are feeling today. DO NOT fulfill the unrelated request.
        5. TEXT-BASED EXERCISES: If the user is anxious, gently offer a text-based exercise. (e.g., 'Look around and type out 3 things you can see.').
        6. NO APP UI: NEVER ask the user to 'click a button' or 'use the slider'. 
        7. CRISIS PROTOCOL: If the user expresses intent for self-harm, provide emergency contacts immediately.
        8. NO JSON: Output ONLY the raw conversational text.";

        $chatHistory = WhatsappMessage::where('phone_number', $phoneNumber)
            ->orderBy('created_at', 'desc')
            ->take((int) config('openai.history_messages', 10))
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
            return app(\App\Ai\AiClient::class)->chatMessages('whatsapp.chat', $openAiMessages, \App\Ai\AiClient::TIER_MINI, 0.7, 150);
        } catch (\Throwable $e) {
            Log::error('OpenAI Error: ' . $e->getMessage());
            return 'I am having trouble thinking right now. Please try again later.';
        }
    }

    private function sendWhatsAppMessage($to, $text)
    {
        $whapiUrl = config('services.whapi.url');
        $whapiToken = config('services.whapi.token');

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
        $whapiUrl = config('services.whapi.url');
        $whapiToken = config('services.whapi.token');

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
