<?php

namespace Tests\Feature;

use App\Interfaces\PaymentGatewayInterface;
use App\Models\ExerciseRun;
use App\Models\MoodEntry;
use App\Models\ThoughtRecord;
use App\Models\User;
use App\Services\AIService;
use App\Services\AiCompanionService;
use App\Services\ExerciseAccessService;
use Database\Seeders\ActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StubAi extends AIService
{
    public static bool $fail = false;

    public function __construct()
    {
    }

    public function getChatCompletion($systemPrompt, $userInstruction, string $feature = 'companion.chat')
    {
        return ['ai_message' => 'ok', 'ui_mode' => 'text_input', 'options' => []];
    }

    public function getUserIntentUsingMini($prompt, $userInstruction, string $feature = 'companion.triage')
    {
        return ['intent' => 'venting_sad'];
    }

    public function getMiniCompletion($systemPrompt, $userInstruction, string $feature, ?int $maxTokens = 80)
    {
        if (self::$fail) {
            throw new \RuntimeException('down');
        }
        return ['distortion' => 'catastrophizing', 'reflection' => 'That sounds heavy.'];
    }
}

class ReframingAndProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private AiCompanionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        StubAi::$fail = false;
        $this->app->bind(AIService::class, StubAi::class);
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
        });

        (new ActivitySeeder)->run();
        $this->user = User::factory()->create(['language' => 'en', 'anonymous_id' => 'anon-1']);
        $this->svc = app(AiCompanionService::class);
    }

    private function buy(string $id): void
    {
        $access = app(ExerciseAccessService::class);
        $o = $access->createOrder($this->user, $id);
        $access->confirmPurchase($this->user, $o['order_id'], 'pay_' . uniqid(), 'sig');
    }

    private function say(string $text): array
    {
        return $this->svc->processInteraction($this->user, 'text_input', $text);
    }

    private function tap(string $id): array
    {
        return $this->svc->processInteraction($this->user, 'buttons', $id);
    }

    private function runReframing(string $after = 'rf_int_3'): array
    {
        $this->buy('reframing_01');
        $this->svc->processInteraction($this->user, 'init_cbt', 'reframing_01');
        $this->say('My boss criticised my report');
        $this->say('I am going to get fired');
        $this->tap('rf_int_9');
        $this->say('I got good feedback last month');
        $this->say('One criticism does not mean I will lose my job');
        return $this->tap($after);
    }

    public function test_reframing_completes_saves_an_encrypted_thought_record_and_consumes_one_session(): void
    {
        // Naming the thinking pattern is an AI feature: needs the exercise AI switch on.
        config(['openai.exercise_ai.enabled' => true]);
        $this->app->bind(\App\Ai\AiClient::class, \Tests\Support\FakeAiClient::class);
        \Tests\Support\FakeAiClient::reset();

        $r = $this->runReframing('rf_int_3');

        $this->assertSame('emoji_slider', $r['ui_mode']);
        $this->assertTrue($r['exercise_completed']);
        $this->assertSame(4, $r['sessions_left']);
        $this->assertStringContainsString('AI:exercise.voice.reframing_close', $r['ai_message']);

        $rec = ThoughtRecord::first();
        $this->assertSame('I am going to get fired', $rec->automatic_thought);
        $this->assertSame('catastrophizing', $rec->distortion);
        $this->assertSame(9, $rec->intensity_before);
        $this->assertSame(3, $rec->intensity_after);

        $raw = DB::table('thought_records')->first();
        $this->assertStringNotContainsString('fired', $raw->automatic_thought, 'stored encrypted');
    }

    public function test_reframing_survives_ai_failure_with_fallback_reflection_and_no_distortion(): void
    {
        StubAi::$fail = true;
        $this->buy('reframing_01');
        $this->svc->processInteraction($this->user, 'init_cbt', 'reframing_01');
        $this->say('Something happened at work');
        $r = $this->say('I am useless');

        $this->assertSame('buttons', $r['ui_mode']);
        $this->assertCount(3, $r['options']);
        $this->assertStringContainsString('really hard to carry', $r['ai_message']);
    }

    public function test_reframing_rejects_empty_answers_and_typed_text_at_a_choice_step(): void
    {
        $this->buy('reframing_01');
        $this->svc->processInteraction($this->user, 'init_cbt', 'reframing_01');

        $r = $this->say('x');
        $this->assertStringContainsString('Take your time', $r['ai_message']);
        $this->assertSame(0, ExerciseRun::first()->state['step']);

        $this->say('Argument with a friend');
        $this->say('They hate me');
        $r = $this->say('quite a lot');
        $this->assertSame('buttons', $r['ui_mode']);
        $this->assertStringContainsString('tap one', $r['ai_message']);
    }

    public function test_unchanged_distress_gets_the_gentle_message_not_the_celebration(): void
    {
        $r = $this->runReframing('rf_int_9');
        $this->assertStringContainsString("okay if it hasn't shifted", $r['ai_message']);
    }

    public function test_progress_endpoint_reports_streak_totals_and_mood_change(): void
    {
        MoodEntry::create(['user_id' => $this->user->id, 'primary_mood' => 3, 'note' => 'x']);
        $this->buy('breathing_01');
        $this->svc->processInteraction($this->user, 'init_cbt', 'breathing_01');
        foreach (range(1, 4) as $_) {
            $this->tap('cbt_ready_breathing_01');
        }
        $this->svc->processInteraction($this->user, 'emoji_slider', '7');

        // A completion yesterday extends the streak to 2.
        ExerciseRun::create([
            'user_id' => $this->user->id, 'activity_id' => 'grounding_01', 'status' => 'completed',
            'completed_at' => now()->subDay(), 'state' => [],
        ]);

        Sanctum::actingAs($this->user);
        $data = $this->getJson('/api/v1/cbt/progress')->assertOk()->json('data');

        $this->assertSame(2, $data['total_completed']);
        $this->assertSame(2, $data['streak_days']);
        $this->assertSame(4.0, (float) $data['avg_mood_change']);
        $this->assertCount(7, $data['last_7_days']);
        $this->assertTrue($data['last_7_days'][6]['done']);
    }

    public function test_thought_records_endpoint_returns_decrypted_records_for_the_owner_only(): void
    {
        $this->runReframing('rf_int_3');
        $other = User::factory()->create(['anonymous_id' => 'anon-2']);

        Sanctum::actingAs($this->user);
        $rows = $this->getJson('/api/v1/cbt/thought-records')->assertOk()->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame('I am going to get fired', $rows[0]['automatic_thought']);

        Sanctum::actingAs($other);
        $this->assertCount(0, $this->getJson('/api/v1/cbt/thought-records')->json('data'));
    }
}
