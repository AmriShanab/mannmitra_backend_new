<?php

namespace Tests\Support;

use App\Ai\AiClient;

/**
 * Stands in for OpenAI. Records every call; by default answers each feature with a recognisable
 * line ("AI:<feature>") so tests can see exactly where AI wording was used.
 */
class FakeAiClient extends AiClient
{
    /** @var array<int, array{feature: string, system: string, user: string}> */
    public static array $calls = [];
    public static bool $fail = false;
    public static ?\Closure $responder = null;

    public static function reset(): void
    {
        self::$calls = [];
        self::$fail = false;
        self::$responder = null;
    }

    public function chatJson(string $feature, string $systemPrompt, string $userInstruction, string $tier = self::TIER_CHAT, float $temperature = 0.7, ?int $maxTokens = null, ?int $timeout = null): array
    {
        self::$calls[] = ['feature' => $feature, 'system' => $systemPrompt, 'user' => $userInstruction];

        if (self::$fail) {
            throw new \RuntimeException('OpenAI down');
        }
        if (self::$responder) {
            return (self::$responder)($feature, $systemPrompt, $userInstruction);
        }
        if ($feature === 'exercise.reframing.analyse') {
            return ['distortion' => 'catastrophizing', 'reflection' => 'AI:reflection'];
        }
        return ['line' => 'AI:' . $feature];
    }
}
