<?php

namespace Tests\Feature;

use App\Ai\AiClient;
use App\Ai\AiResponseValidator;
use App\Models\AiUsageLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiClientTest extends TestCase
{
    use RefreshDatabase;

    private function ok(array $content): array
    {
        return [
            'choices' => [['message' => ['content' => json_encode($content)]]],
            'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 30],
        ];
    }

    public function test_success_returns_json_and_logs_usage_with_the_configured_model(): void
    {
        config(['openai.models.mini' => 'test-mini']);
        Http::fake(['api.openai.com/*' => Http::response($this->ok(['intent' => 'crisis']))]);

        $out = app(AiClient::class)->chatJson('companion.triage', 'sys', 'user', AiClient::TIER_MINI, 0.0, 60);

        $this->assertSame(['intent' => 'crisis'], $out);
        Http::assertSent(fn($req) => $req['model'] === 'test-mini' && $req['max_tokens'] === 60);

        $log = AiUsageLog::first();
        $this->assertSame('companion.triage', $log->feature);
        $this->assertSame('test-mini', $log->model);
        $this->assertSame(120, $log->prompt_tokens);
        $this->assertSame('ok', $log->status);
    }

    public function test_retries_rate_limits_then_succeeds(): void
    {
        config(['openai.retry_sleep_ms' => 0]);
        Http::fake(['api.openai.com/*' => Http::sequence()
            ->push(['error' => 'rate'], 429)
            ->push($this->ok(['ok' => true]))]);

        $out = app(AiClient::class)->chatJson('t', 's', 'u');

        $this->assertSame(['ok' => true], $out);
        Http::assertSentCount(2);
    }

    public function test_does_not_retry_client_errors_and_logs_the_failure(): void
    {
        config(['openai.retry_sleep_ms' => 0]);
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'bad'], 400)]);

        try {
            app(AiClient::class)->chatJson('t', 's', 'u');
            $this->fail('expected exception');
        } catch (\Throwable $e) {
            $this->assertNotNull($e);
        }

        Http::assertSentCount(1);
        $log = AiUsageLog::first();
        $this->assertSame('error', $log->status);
        $this->assertSame(400, $log->http_status);
    }

    public function test_non_json_content_is_an_error(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'not json']]]])]);

        $this->expectException(\RuntimeException::class);
        app(AiClient::class)->chatJson('t', 's', 'u');
    }

    public function test_validator_repairs_bad_nodes(): void
    {
        $v = new AiResponseValidator();

        $n = $v->node(['ai_message' => '  ', 'ui_mode' => 'hologram', 'options' => 'x']);
        $this->assertSame('I am here for you.', $n['ai_message']);
        $this->assertSame('text_input', $n['ui_mode']);
        $this->assertSame([], $n['options']);

        // buttons with no valid options would strand the user
        $n = $v->node(['ai_message' => 'hi', 'ui_mode' => 'buttons', 'options' => [['id' => 1]]]);
        $this->assertSame('text_input', $n['ui_mode']);

        $n = $v->node(['ai_message' => 'hi', 'ui_mode' => 'buttons', 'options' => [['id' => 1, 'label' => 'Yes']]]);
        $this->assertSame([['id' => '1', 'label' => 'Yes']], $n['options']);

        $this->assertNull($v->intent(['intent' => 'made_up'], ['crisis']));
        $this->assertSame('crisis', $v->intent(['intent' => 'crisis'], ['crisis']));
    }
}
