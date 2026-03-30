<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    public function getChatCompletion($systemPrompt, $userInstruction)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o',
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userInstruction]
            ],
            'temperature' => 0.7
        ]);

        if ($response->successful()) {
            return json_decode($response->json('choices.0.message.content'), true);
        }

        throw new \Exception("OpenAI API failed with status: " . $response->status());
    }

    public function getUserIntentUsingMini($prompt, $userInstruction) 
    {
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
            'Content-Type' => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'user', 'content' => $userInstruction]
            ],
            'temperature' => 0.0 
        ]);

        if($response->successful()){
            $content = $response->json('choices.0.message.content');
            return json_decode($content, true); 
        }

        throw new \Exception("OpenAI API failed with status: " . $response->status());
    }


    public function transcribeAudio($audioFile, $languageCode = 'en')
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
