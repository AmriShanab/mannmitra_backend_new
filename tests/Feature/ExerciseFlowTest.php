<?php

namespace Tests\Feature;

use App\Interfaces\PaymentGatewayInterface;
use App\Models\Activities;
use App\Models\ExerciseCredit;
use App\Models\ExerciseRun;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AIService;
use App\Services\AiCompanionService;
use App\Services\ExerciseAccessService;
use Database\Seeders\ActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FakeAiService extends AIService
{
    public static int $calls = 0;
    public static bool $fail = false;

    public function __construct()
    {
    }

    public function getChatCompletion($systemPrompt, $userInstruction, string $feature = 'companion.chat')
    {
        self::$calls++;
        if (self::$fail) {
            throw new \RuntimeException('down');
        }
        return ['ai_message' => 'ok', 'ui_mode' => 'text_input', 'options' => []];
    }

    public function getUserIntentUsingMini($prompt, $userInstruction, string $feature = 'companion.triage')
    {
        self::$calls++;
        return ['intent' => 'venting_sad'];
    }

    public function getMiniCompletion($systemPrompt, $userInstruction, string $feature, ?int $maxTokens = 80)
    {
        self::$calls++;
        if (self::$fail) {
            throw new \RuntimeException('down');
        }
        return ['reaction' => 'Nice one.'];
    }
}

class ExerciseFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private AiCompanionService $svc;
    private ExerciseAccessService $access;

    protected function setUp(): void
    {
        parent::setUp();

        FakeAiService::$calls = 0;
        FakeAiService::$fail = false;
        $this->app->bind(AIService::class, FakeAiService::class);

        // Fake gateway: signatures always valid; orders echo their notes back.
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
        $this->access = app(ExerciseAccessService::class);
    }

    private function buy(string $activityId, ?User $user = null): void
    {
        $user ??= $this->user;
        $order = $this->access->createOrder($user, $activityId);
        $this->access->confirmPurchase($user, $order['order_id'], 'pay_' . uniqid(), 'sig');
    }

    // ----------------------------------------------------------- access / paywall

    public function test_unpaid_exercise_returns_payment_required_and_creates_no_run(): void
    {
        $r = $this->svc->processInteraction($this->user, 'init_cbt', 'breathing_01');

        $this->assertSame('payment_required', $r['ui_mode']);
        $this->assertSame(75, $r['payment']['price']);
        $this->assertSame(0, ExerciseRun::count());
    }

    public function test_pack_is_per_exercise_and_idempotent_per_order(): void
    {
        $order = $this->access->createOrder($this->user, 'breathing_01');
        $this->assertSame(7500, $order['amount']);

        $this->access->confirmPurchase($this->user, $order['order_id'], 'pay_1', 'sig');
        $this->access->confirmPurchase($this->user, $order['order_id'], 'pay_1', 'sig');

        $this->assertSame(1, ExerciseCredit::count());
        $this->assertSame(5, $this->access->sessionsLeft($this->user, 'breathing_01'));
        $this->assertSame(0, $this->access->sessionsLeft($this->user, 'grounding_01'));
    }

    public function test_another_user_cannot_claim_an_order(): void
    {
        $order = $this->access->createOrder($this->user, 'breathing_01');
        $other = User::factory()->create(['anonymous_id' => 'anon-2']);

        $this->expectException(\DomainException::class);
        $this->access->confirmPurchase($other, $order['order_id'], 'pay_1', 'sig');
    }

    // ----------------------------------------------------------- breathing

    public function test_breathing_runs_three_cycles_with_zero_ai_calls_and_consumes_one_session_on_finish(): void
    {
        $this->buy('breathing_01');

        $r = $this->svc->processInteraction($this->user, 'init_cbt', 'breathing_01');
        $this->assertSame('breathing_animation', $r['ui_mode']);
        $this->assertSame(3, $r['exercise']['total_steps']);
        $this->assertSame(5, $this->access->sessionsLeft($this->user, 'breathing_01'));

        // ready + 3 breaths: the first three taps are cycles, the fourth finishes.
        foreach ([1, 2, 3] as $cycle) {
            $r = $this->svc->processInteraction($this->user, 'buttons', 'cbt_ready_breathing_01');
            $this->assertSame('breathing_animation', $r['ui_mode']);
            $this->assertSame($cycle, $r['exercise']['step']);
            $this->assertSame(5, $this->access->sessionsLeft($this->user, 'breathing_01'), 'mid-exercise must not consume');
        }

        $r = $this->svc->processInteraction($this->user, 'buttons', 'cbt_ready_breathing_01');
        $this->assertSame('emoji_slider', $r['ui_mode']);
        $this->assertTrue($r['exercise_completed']);
        $this->assertSame(4, $r['sessions_left']);
        $this->assertSame(0, FakeAiService::$calls, 'breathing must not call the AI');
    }

    public function test_typing_during_breathing_does_not_advance_or_call_ai(): void
    {
        $this->buy('breathing_01');
        $this->svc->processInteraction($this->user, 'init_cbt', 'breathing_01');
        $this->svc->processInteraction($this->user, 'buttons', 'cbt_ready_breathing_01');

        $r = $this->svc->processInteraction($this->user, 'text_input', 'hello?');

        $this->assertSame('breathing_animation', $r['ui_mode']);
        $this->assertSame(1, $r['exercise']['step']);
        $this->assertSame(0, FakeAiService::$calls);
    }

    public function test_mood_slider_after_exercise_gets_one_canned_wrap_up(): void
    {
        $this->buy('breathing_01');
        $this->svc->processInteraction($this->user, 'init_cbt', 'breathing_01');
        foreach (range(1, 4) as $_) {
            $this->svc->processInteraction($this->user, 'buttons', 'cbt_ready_breathing_01');
        }

        $r = $this->svc->processInteraction($this->user, 'emoji_slider', '3');
        $this->assertSame('text_input', $r['ui_mode']);
        $this->assertStringContainsString('heavy', $r['ai_message']);
        $this->assertSame(0, FakeAiService::$calls);
        $this->assertSame(3, ExerciseRun::first()->state['mood_after']);

        // Slider used again later is normal chat, not a second wrap-up.
        $r2 = $this->svc->processInteraction($this->user, 'emoji_slider', '8');
        $this->assertNotSame($r['ai_message'], $r2['ai_message']);
    }

    // ----------------------------------------------------------- grounding

    public function test_grounding_walks_five_senses_in_order_and_survives_ai_failure(): void
    {
        $this->buy('grounding_01');
        $r = $this->svc->processInteraction($this->user, 'init_cbt', 'grounding_01');
        $this->assertStringContainsString('5 things', $r['ai_message']);

        $expected = ['4 things', '3 things', '2 things', '1 thing'];
        foreach ($expected as $i => $needle) {
            FakeAiService::$fail = $i === 1; // one reaction call fails: canned line is used
            $r = $this->svc->processInteraction($this->user, 'text_input', 'something here');
            $this->assertSame('text_input', $r['ui_mode']);
            $this->assertStringContainsString($needle, $r['ai_message']);
            $this->assertSame(5, $this->access->sessionsLeft($this->user, 'grounding_01'));
        }

        FakeAiService::$fail = false;
        $r = $this->svc->processInteraction($this->user, 'text_input', 'sweet tea');
        $this->assertSame('emoji_slider', $r['ui_mode']);
        $this->assertTrue($r['exercise_completed']);
        $this->assertSame(4, $this->access->sessionsLeft($this->user, 'grounding_01'));
    }

    public function test_grounding_does_not_advance_on_empty_answer(): void
    {
        $this->buy('grounding_01');
        $this->svc->processInteraction($this->user, 'init_cbt', 'grounding_01');

        $r = $this->svc->processInteraction($this->user, 'text_input', '.');

        $this->assertStringContainsString('5 things', $r['ai_message']);
        $this->assertSame(0, ExerciseRun::first()->step);
    }

    public function test_crisis_keyword_mid_exercise_still_routes_to_crisis_cards(): void
    {
        $this->buy('grounding_01');
        $this->svc->processInteraction($this->user, 'init_cbt', 'grounding_01');

        $r = $this->svc->processInteraction($this->user, 'text_input', 'I want to die');

        $this->assertSame('crisis_cards', $r['ui_mode']);
        $this->assertCount(3, $r['options']);
    }

    // ----------------------------------------------------------- subscribers / exhaustion

    public function test_exhausted_pack_relocks_and_subscribers_bypass_credits(): void
    {
        $this->buy('breathing_01');
        ExerciseCredit::query()->update(['sessions_used' => 5]);

        $r = $this->svc->processInteraction($this->user, 'init_cbt', 'breathing_01');
        $this->assertSame('payment_required', $r['ui_mode']);

        Subscription::create([
            'user_id' => $this->user->id, 'plan_type' => 'monthly', 'transaction_id' => 't',
            'razorpay_order_id' => 'o_sub', 'amount' => 99, 'starts_at' => now(),
            'expires_at' => now()->addMonth(), 'status' => 'active',
        ]);

        $r = $this->svc->processInteraction($this->user, 'init_cbt', 'breathing_01');
        $this->assertSame('breathing_animation', $r['ui_mode']);
        foreach (range(1, 4) as $_) {
            $this->svc->processInteraction($this->user, 'buttons', 'cbt_ready_breathing_01');
        }
        $this->assertSame(5, (int) ExerciseCredit::sum('sessions_used'), 'subscriber finish consumes nothing');
    }

    public function test_feature_flag_off_falls_back_to_legacy_llm_path(): void
    {
        config(['openai.exercise_state_machine' => false]);
        $this->buy('breathing_01');

        $r = $this->svc->processInteraction($this->user, 'init_cbt', 'breathing_01');

        $this->assertSame('breathing_animation', $r['ui_mode']);
        $this->assertArrayNotHasKey('exercise', $r);

        $this->svc->processInteraction($this->user, 'buttons', 'cbt_ready_breathing_01');
        $this->assertGreaterThan(0, FakeAiService::$calls, 'legacy path uses the LLM');
    }

    public function test_reframing_no_longer_crashes_after_first_turn(): void
    {
        $this->buy('reframing_01');
        $this->svc->processInteraction($this->user, 'init_cbt', 'reframing_01');

        // Triage says venting in the fake, so force the reframing route explicitly.
        $m = new \ReflectionMethod($this->svc, 'handleCbtExerciseRoute');
        $m->setAccessible(true);
        $session = app(\App\Repositories\CompanionRepository::class)->getActiveSession($this->user->id);
        $r = $m->invoke($this->svc, $session, $this->user, 'cbt_reframing', 'I always fail');

        $this->assertSame('text_input', $r['ui_mode']);
    }

    public function test_paywall_switch_off_makes_exercises_free_without_consuming_credits(): void
    {
        config(['services.pricing.exercise.enabled' => false]);

        $r = $this->svc->processInteraction($this->user, 'init_cbt', 'breathing_01');
        $this->assertSame('breathing_animation', $r['ui_mode']);
        foreach (range(1, 4) as $_) {
            $r = $this->svc->processInteraction($this->user, 'buttons', 'cbt_ready_breathing_01');
        }
        $this->assertTrue($r['exercise_completed']);
        $this->assertSame(0, ExerciseCredit::count());

        $catalog = app(\App\Services\ActivityService::class)->getAllActivities($this->user);
        $this->assertTrue($catalog[0]['is_free']);

        config(['services.pricing.exercise.enabled' => true]);
        $this->assertSame('payment_required', $this->svc->processInteraction($this->user, 'init_cbt', 'breathing_01')['ui_mode']);
    }
}
