<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAiService
{
    public function generateChatResponse(array $formattedHistory)
    {
        $response = OpenAI::chat()->create([
            'model' =>'gpt-4o-mini',
            'messages' => $formattedHistory,
            'temperature' => 0.3,
            'response_format' => ['type' => 'json_object'],
        ]);

        $content = $response->choices[0]->message->content;
        return json_decode($content, true);
    }

    public function transcribe($filePath)
    {
        $response = OpenAI::audio()->transcribe([
            'model' => 'whisper-1',
            'file' => fopen($filePath, 'r'), 
            'response_format' => 'verbose_json',
        ]);

        return $response->text;
    }

    public function speak($text)
    {
        $response = OpenAI::audio()->speech([
            'model' => 'tts-1',
            'input' => $text,
            'voice' => 'alloy', 
        ]);

        $fileName = 'ai_response_' . Str::random(20) . '.mp3';
        $filePath = 'voice_messages/' . $fileName;

        Storage::disk('public')->put($filePath, $response);

        return $filePath;
    }

    public function analyze($text, $type = 'daily')
    {
        $systemPrompt = "You are an empathetic mental health journaling assistant.";
        
        if ($type === 'weekly') {
            $systemPrompt .= " The user has provided their journal entries for the last 7 days. 
            Your task:
            1. Identify the main emotional themes of the week.
            2. Highlight any positive moments or progress.
            3. Provide a gentle, 3-sentence reflection on their week.";
        } else {
            $systemPrompt .= " Analyze this journal entry and provide a short, supportive reflection.";
        }

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $text],
            ],
            'temperature' => 0.5,
        ]);

        return $response->choices[0]->message->content;
    }
}