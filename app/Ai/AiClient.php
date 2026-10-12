<?php

namespace App\Ai;

use App\Models\AiUsageLog;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The single place that talks to OpenAI over HTTP. Models, timeouts, retries and token caps
 * come from config/openai.php; every call is recorded in ai_usage_logs.
 */
class AiClient
{
    public const TIER_CHAT = 'chat';
    public const TIER_MINI = 'mini';

    private const BASE_URL = 'https://api.openai.com/v1';

    /**
     * Chat completion that must return a JSON object. Throws on failure after retries.
     *
     * @param string $feature label for usage logs, e.g. "companion.venting"
     */
    public function chatJson(string $feature, string $systemPrompt, string $userInstruction, string $tier = self::TIER_CHAT, float $temperature = 0.7, ?int $maxTokens = null): array
    {
        $model = config("openai.models.$tier");
        $started = microtime(true);
        $httpStatus = null;

        try {
            $response = $this->http(config('openai.request_timeout', 30))
                ->post(self::BASE_URL . '/chat/completions', [
                    'model' => $model,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userInstruction],
                    ],
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens ?? config('openai.max_tokens'),
                ]);
            $httpStatus = $response->status();
            $response->throw();

            $decoded = json_decode((string) $response->json('choices.0.message.content'), true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('OpenAI returned non-JSON content');
            }

            $this->log($feature, $model, $started, 'ok', $httpStatus, $response->json('usage'));
            return $decoded;
        } catch (\Throwable $e) {
            if ($e instanceof RequestException) {
                $httpStatus = $e->response->status();
            }
            $this->log($feature, $model, $started, 'error', $httpStatus);
            throw $e;
        }
    }

    /**
     * Speech-to-text. Returns null on failure so callers can degrade gracefully.
     */
    public function transcribe(string $feature, string $filePath, string $fileName, string $language = 'en'): ?string
    {
        $model = config('openai.models.transcribe');
        $started = microtime(true);
        $httpStatus = null;

        try {
            $response = Http::withToken(config('openai.api_key'))
                ->timeout(60)
                ->attach('file', file_get_contents($filePath), $fileName)
                ->post(self::BASE_URL . '/audio/transcriptions', [
                    'model' => $model,
                    'language' => $language,
                ]);
            $httpStatus = $response->status();
            $response->throw();

            $this->log($feature, $model, $started, 'ok', $httpStatus);
            return $response->json('text');
        } catch (\Throwable $e) {
            $this->log($feature, $model, $started, 'error', $httpStatus);
            Log::error('Whisper API Error: ' . $e->getMessage());
            return null;
        }
    }

    private function http(int $timeout)
    {
        return Http::withToken(config('openai.api_key'))
            ->timeout($timeout)
            // Retry rate limits and server errors only; 4xx request errors will not improve.
            ->retry(
                config('openai.retries', 2),
                config('openai.retry_sleep_ms', 300),
                fn($e) => $e instanceof RequestException
                    ? in_array($e->response->status(), [408, 429, 500, 502, 503, 504], true)
                    : true,
                throw: false
            );
    }

    private function log(string $feature, string $model, float $started, string $status, ?int $httpStatus, ?array $usage = null): void
    {
        try {
            AiUsageLog::create([
                'user_id' => Auth::id(),
                'feature' => $feature,
                'model' => $model,
                'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'status' => $status,
                'http_status' => $httpStatus,
            ]);
        } catch (\Throwable $e) {
            // Telemetry must never break a user's chat.
            Log::warning('ai_usage_logs write failed: ' . $e->getMessage());
        }
    }
}
