<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;

class OpenAiService
{
    public function generateChatResponse(array $formattedHistory)
    {
        $response = OpenAI::chat()->create([
            'model' =>'gpt-4o-mini',
            'messages' => $formattedHistory,
            'temperature' => 0.7,
        ]);

        return $response->choices[0]->message->content;
    }
}