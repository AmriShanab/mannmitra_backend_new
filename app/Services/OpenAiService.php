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
        ]);

        return $response->choices[0]->message->content;
    }

    public function transcribe($filePath)
    {
        $response = OpenAI::audio()->transcribe([
            'model' => 'whisper-1',
            // Now we just open the path string we passed
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
}