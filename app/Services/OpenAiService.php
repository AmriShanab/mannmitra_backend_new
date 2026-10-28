<?php

namespace App\Services;

use App\Ai\AiClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Legacy chat/journal/voice entry points, now routed through App\Ai\AiClient so every call
 * uses the configured cheap models, capped output and the ai_usage_logs table.
 */
class OpenAiService
{
    public function __construct(protected AiClient $client)
    {
    }

    public function generateChatResponse(array $formattedHistory)
    {
        try {
            $content = $this->client->chatMessages('chat.reply', $formattedHistory, AiClient::TIER_CHAT, 0.3, 250, true);
        } catch (\Throwable $e) {
            return null;
        }

        return json_decode($content, true);
    }

    public function transcribe($filePath)
    {
        return $this->client->transcribe('chat.transcribe', $filePath, basename($filePath));
    }

    public function speak($text)
    {
        $audio = $this->client->speak('chat.speak', $text);
        if ($audio === null) {
            return null;
        }

        $filePath = 'voice_messages/ai_response_' . Str::random(20) . '.mp3';
        Storage::disk('public')->put($filePath, $audio);

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
            3. Provide a gentle, 3-sentence reflection on their week.
            Keep the whole answer under 100 words.";
        } else {
            $systemPrompt .= " Analyze this journal entry and provide a short, supportive reflection in under 60 words.";
        }

        return $this->client->chatMessages('journal.' . $type, [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $text],
        ], AiClient::TIER_MINI, 0.5, 200);
    }
}
