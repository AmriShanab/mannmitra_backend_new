<?php

namespace Tests\Feature;

use App\Ai\AiClient;
use App\Interfaces\PaymentGatewayInterface;
use App\Models\MoodEntry;
use App\Models\ThoughtRecord;
use App\Models\User;
use App\Services\AIService;
use App\Services\AiCompanionService;
use App\Services\ExerciseAccessService;
use Database\Seeders\ActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeAiClient;
use Tests\TestCase;

/** Chat-level AI (triage etc.) must never be touched by these exercises. */
class UnusedChatAi extends AIService
{
    public function __construct()
    {
    }

    public function getChatCompletion($systemPrompt, $userInstruction, string $feature = 'companion.chat')
    {
        throw new \LogicException('exercise must not use the chat AI');
    }

    public function getUserIntentUsingMini($prompt, $userInstruction, string $feature = 'companion.triage')
    {
        throw new \LogicException('exercise must not use the chat AI');
    }
}

class ExerciseAiToggleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private AiCompanionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        FakeAiClient::reset();
        Cache::flush();
        config(['openai.exercise_ai.enabled' => true]);

        $this->app->bind(AiClient::class, FakeAiClient::class);
        $this->app->bind(AIService::class, UnusedChatAi::class);
        $this->app->bind(PaymentGatewayInterface::class, fn() => new class implements PaymentGatewayInterface {
            private static array $orders = [];
            public function createOrder($amount, $currency, array $notes = [])
            {
                $id = 'order_' . count(self::$orders);
                self::$orders[$id] = ['id' => $id, 'amount' => $amount, 'currency' => 'INR', 'status' => 'created', 'notes' => $notes];
                return self::$orders[$id] + ['key' => 'k'];
            }
            public function verifyPayment($a) { return true; }
            public function fetchOrder(string $id) { return self::$orders[$id]; }
            public function refundPayment(string $paymentId, array $notes = []) { return ['id' => 'rfnd_test', 'status' => 'processed']; }
        });

        (new ActivitySeeder)->run();
        $this->user = User::factory()->create(['language' => 'en', 'anonymous_id' => 'anon-1']);
        $this->svc = app(AiCompanionService::class);

        $access = app(ExerciseAccessService::class);
        foreach (['breathing_01', 'grounding_01', 'reframing_01', 'butterfly_hug_01'] as $id) {
            $o = $access->createOrder($this->user, $id);
            $access->confirmPurchase($this->user, $o['order_id'], 'pay_' . $id, 'sig');
        }
    }

    private function start(string $id): array
    {
        return $this->svc->processInteraction($this->user, 'init_cbt', $id);
    }

    private function say(string $text): array
    {
        return $this->svc->processInteraction($this->user, 'text_input', $text);
    }

    private function tap(string $id): array
    {
        return $this->svc->processInteraction($this->user, 'buttons', $id);
    }

    private function features(): array
    {
        return array_column(FakeAiClient::$calls, 'feature');
    }

    // ------------------------------------------------------------------ switch off

    public function test_switch_off_makes_no_ai_calls_in_any_exercise(): void
    {
        config(['openai.exercise_ai.enabled' => false]);

        $this->start('grounding_01');
        foreach (['mug and lamp', 'floor, sleeve', 'fan, rain', 'tea', 'sugar'] as $a) {
            $r = $this->say($a);
        }
        $this->assertSame('emoji_slider', $r['ui_mode']);

        $this->start('breathing_01');
        $this->say('i feel dizzy');
        foreach (range(1, 4) as $_) {
            $this->tap('cbt_ready_breathing_01');
        }

        $this->start('reframing_01');
        $this->say('Argument at work');
        $this->say('I always mess up');
        $this->tap('rf_int_9');
        $this->say('I did fine last week');
        $this->say('One mistake is not everything');
        $this->tap('rf_int_3');

        $this->start('butterfly_hug_01');
        $this->say('how long?');

        $this->assertSame([], FakeAiClient::$calls, 'switch off: OpenAI is never contacted');
    }

    public function test_switch_off_uses_the_fixed_lines(): void
    {
        config(['openai.exercise_ai.enabled' => false]);
        $this->start('grounding_01');

        $r = $this->say('a red mug');

        $this->assertStringContainsString('Thank you for sharing that.', $r['ai_message']);
        $this->assertStringNotContainsString('AI:', $r['ai_message']);
    }

    // ------------------------------------------------------------------ switch on: grounding

    public function test_grounding_reacts_to_what_the_user_said_and_closes_with_their_own_words(): void
    {
        $this->start('grounding_01');

        $r = $this->say('a red mug and my laptop');
        $this->assertStringStartsWith('AI:exercise.voice.grounding_react', $r['ai_message']);
        $this->assertStringContainsString('4 things', $r['ai_message'], 'the next question still comes from the app');
        $this->assertStringContainsString('red mug', FakeAiClient::$calls[0]['system'], 'the model is told what they said');
        $this->assertStringContainsString('SEE', FakeAiClient::$calls[0]['system']);

        foreach (['my socks', 'a fan', 'tea'] as $a) {
            $this->say($a);
        }
        $r = $this->say('sugar');

        $this->assertSame('emoji_slider', $r['ui_mode']);
        $this->assertTrue($r['exercise_completed']);
        $this->assertStringStartsWith('AI:exercise.voice.grounding_close', $r['ai_message']);
        $this->assertStringContainsString('How are you feeling right now?', $r['ai_message']);
        $closeCall = end(FakeAiClient::$calls);
        $this->assertStringContainsString('red mug', $closeCall['system'], 'the closing line is given everything they named');
        $this->assertStringContainsString('taste: sugar', $closeCall['system']);
    }

    // ------------------------------------------------------------------ switch on: failures and safety

    public function test_ai_failure_falls_back_to_fixed_text_and_pauses_the_ai(): void
    {
        FakeAiClient::$fail = true;
        $this->start('grounding_01');

        $r = $this->say('a red mug');
        $this->assertStringContainsString('Thank you for sharing that.', $r['ai_message']);
        $this->assertStringContainsString('4 things', $r['ai_message']);

        $this->say('socks');
        $this->say('a fan');
        $this->assertCount(1, FakeAiClient::$calls, 'after one failure the AI is left alone for a while');

        // The session still finishes normally.
        $this->say('tea');
        $r = $this->say('sugar');
        $this->assertTrue($r['exercise_completed']);
        $this->assertStringContainsString('proud of you', $r['ai_message']);
    }

    public function test_a_line_that_asks_a_question_is_rejected(): void
    {
        FakeAiClient::$responder = fn() => ['line' => 'How does that feel?'];
        $this->start('grounding_01');

        $r = $this->say('a red mug');

        $this->assertStringNotContainsString('How does that feel?', $r['ai_message']);
        $this->assertStringContainsString('Thank you for sharing that.', $r['ai_message']);
    }

    public function test_overlong_lines_are_rejected(): void
    {
        FakeAiClient::$responder = fn() => ['line' => str_repeat('word ', 80)];
        $this->start('grounding_01');

        $r = $this->say('a red mug');

        $this->assertStringContainsString('Thank you for sharing that.', $r['ai_message']);
    }

    // ------------------------------------------------------------------ breathing / butterfly hug

    public function test_typing_during_breathing_gets_a_personal_reply_and_the_same_breath(): void
    {
        $this->start('breathing_01');
        $this->tap('cbt_ready_breathing_01');

        $r = $this->say('i feel a bit dizzy');

        $this->assertStringStartsWith('AI:exercise.voice.interrupt', $r['ai_message']);
        $this->assertStringContainsString('Take your time', $r['ai_message']);
        $this->assertSame(1, $r['exercise']['step'], 'still on the same breath');
        $this->assertStringContainsString('dizzy', end(FakeAiClient::$calls)['system']);
    }

    public function test_openings_use_the_users_mood_and_closings_are_personal(): void
    {
        MoodEntry::create(['user_id' => $this->user->id, 'primary_mood' => 3, 'note' => 'x']);

        $r = $this->start('breathing_01');
        $this->assertStringStartsWith('AI:exercise.voice.open', $r['ai_message']);
        $this->assertStringContainsString("Welcome to the Breathing Exercise", $r['ai_message']);
        $this->assertStringContainsString('3/10', FakeAiClient::$calls[0]['system']);

        foreach (range(1, 3) as $_) {
            $this->tap('cbt_ready_breathing_01');
        }
        $r = $this->tap('cbt_ready_breathing_01');
        $this->assertTrue($r['exercise_completed']);
        $this->assertStringStartsWith('AI:exercise.voice.close', $r['ai_message']);
        $this->assertStringContainsString('3/10', end(FakeAiClient::$calls)['system'], 'mood before survives to the closing line');
    }

    public function test_butterfly_hug_keeps_the_mood_through_the_minute_and_personalises_both_ends(): void
    {
        MoodEntry::create(['user_id' => $this->user->id, 'primary_mood' => 2, 'note' => 'x']);

        $r = $this->start('butterfly_hug_01');
        $this->assertStringStartsWith('AI:exercise.voice.open', $r['ai_message']);

        $this->tap('cbt_ready_butterfly_hug_01');
        $this->travel(61)->seconds();
        $r = $this->tap('cbt_ready_butterfly_hug_01');

        $this->assertTrue($r['exercise_completed']);
        $this->assertStringStartsWith('AI:exercise.voice.close', $r['ai_message']);
        $this->assertStringContainsString('2/10', end(FakeAiClient::$calls)['system']);
    }

    // ------------------------------------------------------------------ reframing

    public function test_reframing_answers_each_step_and_still_saves_the_record_with_the_pattern(): void
    {
        $r = $this->start('reframing_01');
        $this->assertSame([], FakeAiClient::$calls, 'the opening question is fixed');

        $r = $this->say('My boss criticised my report');
        $this->assertStringStartsWith('AI:exercise.voice.reframing_situation', $r['ai_message']);
        $this->assertStringContainsString('What thought went through your mind', $r['ai_message']);

        $r = $this->say('I am going to get fired');
        $this->assertStringStartsWith('AI:reflection', $r['ai_message']);
        $this->tap('rf_int_9');

        $r = $this->say('I got good feedback last month');
        $this->assertStringStartsWith('AI:exercise.voice.reframing_evidence', $r['ai_message']);
        $this->assertStringContainsString('fired', end(FakeAiClient::$calls)['system'], 'knows the thought it is answering');

        $r = $this->say('One criticism does not mean I lose my job');
        $this->assertStringStartsWith('AI:exercise.voice.reframing_balanced', $r['ai_message']);
        $this->assertSame('buttons', $r['ui_mode']);

        $r = $this->tap('rf_int_3');
        $this->assertStringStartsWith('AI:exercise.voice.reframing_close', $r['ai_message']);
        $this->assertStringContainsString('One criticism does not mean I lose my job', $r['ai_message']);
        $this->assertTrue($r['exercise_completed']);
        $this->assertSame(4, $r['sessions_left']);

        $record = ThoughtRecord::first();
        $this->assertSame('catastrophizing', $record->distortion);
        $this->assertSame('One criticism does not mean I lose my job', $record->balanced_thought);
    }

    public function test_rejected_answers_do_not_cost_an_ai_call(): void
    {
        $this->start('reframing_01');

        $this->say('x'); // too short

        $this->assertSame([], FakeAiClient::$calls);
    }
}
