<?php

namespace Tests\Feature;

use App\Interfaces\PaymentGatewayInterface;
use App\Models\ExerciseCredit;
use App\Models\ExerciseRun;
use App\Models\User;
use App\Services\AIService;
use App\Services\AiCompanionService;
use App\Services\ExerciseAccessService;
use Database\Seeders\ActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Any AI call in a Butterfly Hug session fails the test. */
class NoAiAllowed extends AIService
{
    public function __construct()
    {
    }

    public function getChatCompletion($systemPrompt, $userInstruction, string $feature = 'companion.chat')
    {
        throw new \LogicException('Butterfly Hug must not call the AI');
    }

    public function getUserIntentUsingMini($prompt, $userInstruction, string $feature = 'companion.triage')
    {
        throw new \LogicException('Butterfly Hug must not call the AI');
    }

    public function getMiniCompletion($systemPrompt, $userInstruction, string $feature, ?int $maxTokens = 80)
    {
        throw new \LogicException('Butterfly Hug must not call the AI');
    }
}

class ButterflyHugTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private AiCompanionService $svc;
    private ExerciseAccessService $access;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(AIService::class, NoAiAllowed::class);
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
        $this->access = app(ExerciseAccessService::class);

        $order = $this->access->createOrder($this->user, 'butterfly_hug_01');
        $this->access->confirmPurchase($this->user, $order['order_id'], 'pay_1', 'sig');
    }

    private function ready(): array
    {
        return $this->svc->processInteraction($this->user, 'buttons', 'cbt_ready_butterfly_hug_01');
    }

    public function test_it_is_a_priced_catalog_exercise_and_locked_until_paid(): void
    {
        $other = User::factory()->create(['anonymous_id' => 'anon-2']);

        $r = $this->svc->processInteraction($other, 'init_cbt', 'butterfly_hug_01');

        $this->assertSame('payment_required', $r['ui_mode']);
        $this->assertSame(75, $r['payment']['price']);
    }

    public function test_full_session_runs_with_no_ai_and_consumes_one_session_only_at_the_end(): void
    {
        $r = $this->svc->processInteraction($this->user, 'init_cbt', 'butterfly_hug_01');
        $this->assertSame('buttons', $r['ui_mode']);
        $this->assertStringContainsString('Butterfly Hug', $r['ai_message']);

        $r = $this->ready();
        $this->assertSame('butterfly_hug', $r['ui_mode']);
        $this->assertSame(60, $r['exercise']['duration_seconds']);
        $this->assertSame(1200, $r['exercise']['tap_ms']);
        $this->assertSame(5, $this->access->sessionsLeft($this->user, 'butterfly_hug_01'));

        $this->travel(61)->seconds();
        $r = $this->ready();

        $this->assertSame('emoji_slider', $r['ui_mode']);
        $this->assertTrue($r['exercise_completed']);
        $this->assertSame(4, $r['sessions_left']);
    }

    public function test_finishing_early_does_not_complete_or_consume_a_session(): void
    {
        $this->svc->processInteraction($this->user, 'init_cbt', 'butterfly_hug_01');
        $this->ready();

        $this->travel(20)->seconds();
        $r = $this->ready();

        $this->assertSame('butterfly_hug', $r['ui_mode']);
        $this->assertArrayNotHasKey('exercise_completed', $r);
        $this->assertSame(5, $this->access->sessionsLeft($this->user, 'butterfly_hug_01'));
        $this->assertSame('started', ExerciseRun::first()->status);
    }

    public function test_typing_during_the_minute_keeps_the_exercise_going(): void
    {
        $this->svc->processInteraction($this->user, 'init_cbt', 'butterfly_hug_01');
        $this->ready();

        $r = $this->svc->processInteraction($this->user, 'text_input', 'how long is left?');

        $this->assertSame('butterfly_hug', $r['ui_mode']);
        $this->assertSame('started', ExerciseRun::first()->status);
    }

    public function test_hinglish_text_is_used_for_hindi_users(): void
    {
        $this->user->update(['language' => 'hi']);

        $r = $this->svc->processInteraction($this->user, 'init_cbt', 'butterfly_hug_01');

        $this->assertStringContainsString('Chaliye Butterfly Hug', $r['ai_message']);
        $this->assertSame('Main taiyaar hoon', $r['options'][0]['label']);
    }

    public function test_crisis_keyword_during_the_exercise_still_gets_crisis_support(): void
    {
        $this->svc->processInteraction($this->user, 'init_cbt', 'butterfly_hug_01');
        $this->ready();

        // The crisis route calls the AI for its message; with the AI down it must still answer.
        $r = $this->svc->processInteraction($this->user, 'text_input', 'I want to die');

        $this->assertSame('crisis_cards', $r['ui_mode']);
        $this->assertCount(4, $r['options']);
        $this->assertSame('14416', $r['options'][0]['id'], 'Tele-MANAS is offered first');
    }
}
