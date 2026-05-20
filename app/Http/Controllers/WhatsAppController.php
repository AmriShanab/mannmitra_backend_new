<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    public function handleWebhook(Request $request)
    {
        Log::info('Whapi Webhook Received: ', $request->all());

        $messages = $request->input('messages', []);
        if(empty($messages)) {
            Log::warning('No messages found in the webhook payload.');
           return response()->json(['status' => 'ignored', 'reason' => 'No messages found']);
        }

        $messages = $messages[0];

        if (isset($message['from_me']) && $message['from_me'] === true) {
            return response()->json(['status' => 'ignored', 'reason' => 'Message is from bot']);
        }

        $senderId = $message['chat_id'] ?? $message['from'] ?? null;
        $userText = $message['text']['body'] ?? null;

        if (!$userText) {
            return response()->json(['status' => 'ignored', 'reason' => 'Not a text message']);
        }

        $cleanNumber = explode('@', $senderId)[0];

        $allowedNumbers = [
            '94722561060',
        ];

        if (!in_array($cleanNumber, $allowedNumbers)) {
            Log::warning('Unauthorized sender: ' . $cleanNumber);
            return response()->json(['status' => 'ignored', 'reason' => 'Unauthorized sender']);
        }

        $aiResponseText = $this->getOpenAiResponse($userText);

        $this->sendWhatsAppMessage($senderId, $aiResponseText);

        return response()->json(['status' => 'success']);
    }

    private function getOpenAiResponse($userText)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo', // or gpt-4o-mini
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are Mann Mitra, a helpful and friendly mental health companion on WhatsApp. Keep your answers concise, warm, and use emojis naturally.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $userText
                    ]
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

        Http::withHeaders([
            'Authorization' => 'Bearer ' . $whapiToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post("{$whapiUrl}/messages/text", [
            'to' => $to,
            'body' => $text,
        ]);
    }
}
