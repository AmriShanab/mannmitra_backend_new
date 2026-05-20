<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    public function handleWebhook(Request $request)
    {
        Log::info('1. Whapi Webhook Received: ', $request->all());

        $messages = $request->input('messages', []);
        if (empty($messages)) {
            Log::warning('No messages found in the webhook payload.');
            return response()->json(['status' => 'ignored', 'reason' => 'No messages found']);
        }

        // BUG FIX: Changed $messages to $message (singular)
        $message = $messages[0];

        if (isset($message['from_me']) && $message['from_me'] === true) {
            Log::info('2. Ignored: Message is from bot');
            return response()->json(['status' => 'ignored', 'reason' => 'Message is from bot']);
        }

        $senderId = $message['chat_id'] ?? $message['from'] ?? null;
        $userText = $message['text']['body'] ?? null;

        if (!$userText) {
            Log::info('3. Ignored: Not a text message');
            return response()->json(['status' => 'ignored', 'reason' => 'Not a text message']);
        }

        Log::info('4. Text extracted successfully: ' . $userText);

        $cleanNumber = explode('@', $senderId)[0];

        $allowedNumbers = [
            '94722561060',
            '94783099340'
        ];

        if (!in_array($cleanNumber, $allowedNumbers)) {
            Log::warning('5. Unauthorized sender: ' . $cleanNumber);
            return response()->json(['status' => 'ignored', 'reason' => 'Unauthorized sender']);
        }

        Log::info('6. Sending to OpenAI...');
        $aiResponseText = $this->getOpenAiResponse($userText);

        Log::info('7. OpenAI replied: ' . $aiResponseText);

        $this->sendWhatsAppMessage($senderId, $aiResponseText);

        return response()->json(['status' => 'success']);
    }

    private function getOpenAiResponse($userText)
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

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userText]
                ],
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

        Log::info("8. Sending back to Whapi... To: $to");

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $whapiToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post("{$whapiUrl}/messages/text", [
            'to' => $to,
            'body' => $text,
        ]);

        if ($response->successful()) {
            Log::info('9. SUCCESS! Message sent to WhatsApp.');
        } else {
            Log::error('9. WHAPI ERROR: ' . $response->body());
        }
    }
}
