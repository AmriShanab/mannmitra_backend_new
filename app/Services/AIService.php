<?php

namespace App\Services;

use App\Ai\AiClient;

/**
 * Compatibility facade over App\Ai\AiClient so existing callers (companion, journal commands,
 * WhatsApp) keep their signatures while sharing one configured, logged OpenAI client.
 */
class AIService
{
    public function __construct(protected AiClient $client)
    {
    }

    public function getChatCompletion($systemPrompt, $userInstruction, string $feature = 'companion.chat')
    {
        return $this->client->chatJson($feature, $systemPrompt, $userInstruction, AiClient::TIER_CHAT, 0.7);
    }

    public function getUserIntentUsingMini($prompt, $userInstruction, string $feature = 'companion.triage')
    {
        return $this->client->chatJson($feature, $prompt, $userInstruction, AiClient::TIER_MINI, 0.0, 60);
    }

    /**
     * Cheap, short JSON reply (reactions, classifications) on the mini model.
     */
    public function getMiniCompletion($systemPrompt, $userInstruction, string $feature, ?int $maxTokens = 80)
    {
        return $this->client->chatJson($feature, $systemPrompt, $userInstruction, AiClient::TIER_MINI, 0.7, $maxTokens);
    }

    public function transcribeAudio($audioFile, $languageCode = 'en')
    {
        $text = $this->client->transcribe(
            'companion.transcribe',
            $audioFile->getRealPath(),
            $audioFile->getClientOriginalName(),
            $languageCode
        );

        return $text ?? '[Error processing voice note]';
    }
}
