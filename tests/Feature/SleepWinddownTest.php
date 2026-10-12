<?php

namespace Tests\Feature;

use App\Ai\AiClient;
use App\Domain\Exercises\ExerciseRecommender;
use App\Domain\Exercises\SleepScripts;
use App\Interfaces\PaymentGatewayInterface;
use App\Models\ExerciseRun;
use App\Models\SleepLog;
use App\Models\User;
use App\Services\AIService;
use App\Services\AiCompanionService;
use App\Services\ExerciseAccessService;
use Database\Seeders\ActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeAiClient;
use Tests\TestCase;

class SleepNoChatAi extends AIService
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

class SleepWinddownTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private AiCompanionService $svc;
    private ExerciseAccessService $access;

    protected function setUp(): void
    {
        parent::setUp();
        FakeAiClient::reset();
        Cache::flush();
        config(['openai.exercise_ai.enabled' => false]);

        $this->app->bind(AiClient::class, FakeAiClient::class);
        $this->app->bind(AIService::class, SleepNoChatAi::class);
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

        $order = $this->access->createOrder($this->user, 'sleep_winddown_01');
        $this->access->confirmPurchase($this->user, $order['order_id'], 'pay_1', 'sig');
    }

    private function start(): array
    {
        return $this->svc->processInteraction($this->user, 'init_cbt', 'sleep_winddown_01');
    }

    private function choose(string $id): array
    {
        return $this->svc->processInteraction($this->user, 'buttons', $id);
    }

    private function finishButton(): array
    {
        return $this->svc->processInteraction($this->user, 'buttons', 'cbt_ready_sleep_winddown_01');
    }

    /** Walks log -> checklist -> mode and returns the session node. */
    private function toSession(string $mode = 'sleep_mode_body', string $checks = 'sleep_chk:screens,bedtime'): array
    {
        $this->start();
        $this->choose('sleep_q_1');
        $this->choose('sleep_h_5.5');
        $this->choose($checks);
        return $this->choose($mode);
    }

    // ------------------------------------------------------------------ the flow

    public function test_full_flow_with_no_ai_saves_the_log_and_consumes_one_session_only_at_the_end(): void
    {
        $r = $this->start();
        $this->assertSame('buttons', $r['ui_mode']);
        $this->assertStringContainsString('How did you sleep last night', $r['ai_message']);
        $this->assertSame(['sleep_q_1', 'sleep_q_2', 'sleep_q_3', 'sleep_skip'], array_column($r['options'], 'id'));

        $r = $this->choose('sleep_q_1');
        $this->assertStringContainsString("sorry it was a rough night", $r['ai_message']);
        $this->assertStringContainsString('how many hours', $r['ai_message']);

        $r = $this->choose('sleep_h_5.5');
        $this->assertSame('sleep_checklist', $r['ui_mode']);
        $this->assertCount(5, $r['exercise']['items']);
        $this->assertSame('screens', $r['exercise']['items'][0]['id']);

        $r = $this->choose('sleep_chk:screens,bedtime');
        $this->assertSame('buttons', $r['ui_mode']);
        $this->assertSame(['sleep_mode_body', 'sleep_mode_story'], array_column($r['options'], 'id'));

        $r = $this->choose('sleep_mode_body');
        $this->assertSame('sleep_session', $r['ui_mode']);
        $this->assertSame('body', $r['exercise']['mode']);
        $this->assertGreaterThan(120, $r['exercise']['total_seconds']);
        $this->assertSame(5, $this->access->sessionsLeft($this->user, 'sleep_winddown_01'), 'not consumed before the end');

        $this->travel($r['exercise']['total_seconds'] + 1)->seconds();
        $r = $this->finishButton();

        $this->assertSame('emoji_slider', $r['ui_mode']);
        $this->assertTrue($r['exercise_completed']);
        $this->assertSame(4, $r['sessions_left']);
        $this->assertStringContainsString('2 of 5 helpful habits', $r['ai_message']);
        $this->assertSame([], FakeAiClient::$calls, 'no AI with the switch off');

        $log = SleepLog::first();
        $this->assertSame(5.5, $log->hours);
        $this->assertSame(1, $log->quality);
        $this->assertSame(['screens', 'bedtime'], $log->checklist);
        $this->assertSame(now()->toDateString(), $log->log_date->toDateString());
    }

    public function test_the_session_cannot_be_tapped_through_but_may_end_once_half_has_played(): void
    {
        $node = $this->toSession();
        $total = $node['exercise']['total_seconds'];

        $this->travel(10)->seconds();
        $r = $this->finishButton();
        $this->assertSame('sleep_session', $r['ui_mode']);
        $this->assertArrayNotHasKey('exercise_completed', $r);
        $this->assertSame(5, $this->access->sessionsLeft($this->user, 'sleep_winddown_01'));

        $this->travel((int) ceil($total * 0.5))->seconds(); // a sleeper may stop early, after half
        $r = $this->finishButton();
        $this->assertTrue($r['exercise_completed']);
    }

    public function test_story_mode_has_its_own_script(): void
    {
        $r = $this->toSession('sleep_mode_story');

        $this->assertSame('story', $r['exercise']['mode']);
        $this->assertStringContainsString('lake', $r['exercise']['lines'][0]['text']);
        $this->assertNotEmpty($r['exercise']['lines'][0]['seconds']);
    }

    public function test_skipping_the_log_saves_no_entry_but_still_works(): void
    {
        $this->start();
        $r = $this->choose('sleep_skip');
        $this->assertSame('sleep_checklist', $r['ui_mode']);

        $this->choose('sleep_chk:');
        $node = $this->choose('sleep_mode_body');
        $this->travel($node['exercise']['total_seconds'])->seconds();
        $r = $this->finishButton();

        $this->assertTrue($r['exercise_completed']);
        $this->assertSame(0, SleepLog::count());
        $this->assertStringNotContainsString('helpful habits', $r['ai_message']);
    }

    public function test_when_last_night_is_already_logged_the_log_questions_are_skipped(): void
    {
        SleepLog::create(['user_id' => $this->user->id, 'log_date' => now()->toDateString(), 'hours' => 7, 'quality' => 3]);

        $r = $this->start();

        $this->assertSame('sleep_checklist', $r['ui_mode']);
    }

    public function test_unknown_checklist_ids_and_invalid_choices_are_ignored(): void
    {
        $this->start();
        $r = $this->choose('sleep_h_9.5');
        $this->assertStringContainsString('choose one of the options', $r['ai_message']);
        $this->assertSame('buttons', $r['ui_mode']);

        $this->choose('sleep_q_3');
        $this->choose('sleep_h_7.5');
        $this->choose('sleep_chk:screens,hacked,room');
        $node = $this->choose('sleep_mode_body');
        $this->travel($node['exercise']['total_seconds'])->seconds();
        $this->finishButton();

        $this->assertSame(['screens', 'room'], SleepLog::first()->checklist);
    }

    public function test_typing_does_not_derail_any_phase(): void
    {
        $this->start();

        $r = $this->svc->processInteraction($this->user, 'text_input', 'can i just talk?');

        $this->assertSame('buttons', $r['ui_mode']);
        $this->assertStringContainsString('choose one of the options', $r['ai_message']);
        $this->assertSame('started', ExerciseRun::first()->status);
    }

    public function test_hinglish_for_hindi_users(): void
    {
        $this->user->update(['language' => 'hi']);

        $r = $this->start();
        $this->assertStringContainsString('Kal raat aapki neend kaisi rahi', $r['ai_message']);

        $this->choose('sleep_skip');
        $r = $this->choose('sleep_chk:');
        $this->assertSame('Body scan', $r['options'][0]['label']);
        $r = $this->choose('sleep_mode_story');
        $this->assertStringContainsString('shaant jheel', $r['exercise']['lines'][0]['text']);
    }

    public function test_crisis_phrases_still_win_during_the_exercise(): void
    {
        $this->start();

        $r = $this->svc->processInteraction($this->user, 'text_input', 'I want to die');

        $this->assertSame('crisis_cards', $r['ui_mode']);
    }

    // ------------------------------------------------------------------ with AI

    public function test_with_ai_it_reacts_to_how_they_slept_and_says_goodnight_personally(): void
    {
        config(['openai.exercise_ai.enabled' => true]);

        $this->start();
        $r = $this->choose('sleep_q_1');
        $this->assertStringStartsWith('AI:exercise.voice.sleep_react', $r['ai_message']);
        $this->assertStringContainsString('how many hours', $r['ai_message'], 'the next question stays fixed');
        $this->assertStringContainsString('poorly', FakeAiClient::$calls[0]['system']);

        $this->choose('sleep_h_6.5');
        $this->choose('sleep_chk:room');
        $node = $this->choose('sleep_mode_body');
        $this->travel($node['exercise']['total_seconds'])->seconds();
        $r = $this->finishButton();

        $this->assertStringStartsWith('AI:exercise.voice.sleep_close', $r['ai_message']);
        $this->assertStringContainsString('How are you feeling right now?', $r['ai_message']);
    }

    public function test_with_ai_down_the_fixed_lines_are_used(): void
    {
        config(['openai.exercise_ai.enabled' => true]);
        FakeAiClient::$fail = true;

        $this->start();
        $r = $this->choose('sleep_q_3');
        $this->assertStringContainsString('Glad to hear it', $r['ai_message']);
        $this->choose('sleep_h_7.5');
        $this->choose('sleep_chk:');
        $node = $this->choose('sleep_mode_body');
        $this->travel($node['exercise']['total_seconds'])->seconds();
        $r = $this->finishButton();

        $this->assertTrue($r['exercise_completed']);
        $this->assertStringContainsString("Sleep well", $r['ai_message']);
    }

    // ------------------------------------------------------------------ scripts / recommendation

    public function test_every_script_line_is_paced_and_sessions_are_a_sensible_length(): void
    {
        foreach (['en', 'hi'] as $lang) {
            foreach ([SleepScripts::BODY, SleepScripts::STORY] as $mode) {
                $lines = SleepScripts::lines($mode, $lang);
                $this->assertGreaterThan(10, count($lines));
                foreach ($lines as $l) {
                    $this->assertNotSame('', trim($l['text']));
                    $this->assertGreaterThanOrEqual(7, $l['seconds']);
                }
                $total = SleepScripts::totalSeconds($lines);
                $this->assertGreaterThan(120, $total, "$lang $mode too short");
                $this->assertLessThan(480, $total, "$lang $mode too long");
            }
        }
    }

    public function test_late_evening_recommends_it_until_done_tonight(): void
    {
        $this->travelTo(now()->setTime(22, 15));
        $ids = ['reframing_01', 'breathing_01', 'grounding_01', 'butterfly_hug_01', 'gratitude_01', 'sleep_winddown_01'];

        $this->assertSame('sleep_winddown_01', app(ExerciseRecommender::class)->pick($this->user, $ids));

        ExerciseRun::create(['user_id' => $this->user->id, 'activity_id' => 'sleep_winddown_01', 'status' => 'completed', 'completed_at' => now(), 'state' => []]);
        $this->assertSame('breathing_01', app(ExerciseRecommender::class)->pick($this->user, $ids));
    }

    // ------------------------------------------------------------------ the free sleep log

    public function test_the_log_endpoint_is_free_validates_and_replaces_the_same_night(): void
    {
        $other = User::factory()->create(['anonymous_id' => 'free-user']); // never bought anything
        Sanctum::actingAs($other);

        $this->postJson('/api/v1/sleep/logs', ['hours' => 6.5, 'quality' => 2])->assertStatus(201);
        $this->postJson('/api/v1/sleep/logs', ['hours' => 8, 'quality' => 3])->assertStatus(201);

        $this->assertSame(1, SleepLog::where('user_id', $other->id)->count(), 'same night replaced, not duplicated');
        $data = $this->getJson('/api/v1/sleep/logs')->assertOk()->json('data');
        $this->assertEquals(8.0, $data['entries'][0]['hours']);
        $this->assertTrue($data['summary']['logged_today']);

        $this->postJson('/api/v1/sleep/logs', ['hours' => 30, 'quality' => 2])->assertStatus(422);
        $this->postJson('/api/v1/sleep/logs', ['hours' => 7, 'quality' => 4])->assertStatus(422);
        $this->postJson('/api/v1/sleep/logs', ['hours' => 7, 'quality' => 2, 'date' => now()->addDay()->toDateString()])->assertStatus(422);
        $this->postJson('/api/v1/sleep/logs', ['hours' => 7, 'quality' => 2, 'date' => now()->subDays(90)->toDateString()])->assertStatus(422);
    }

    public function test_the_week_summary_averages_only_the_last_seven_days_and_is_private(): void
    {
        foreach ([[0, 6, 1], [1, 8, 3], [2, 7, 2], [20, 2, 1]] as [$ago, $h, $q]) {
            SleepLog::create(['user_id' => $this->user->id, 'log_date' => now()->subDays($ago)->toDateString(), 'hours' => $h, 'quality' => $q]);
        }

        Sanctum::actingAs($this->user);
        $s = $this->getJson('/api/v1/sleep/logs')->json('data.summary');
        $this->assertSame(3, $s['entries_7d']);
        $this->assertEquals(7.0, $s['avg_hours_7d']);
        $this->assertEquals(2.0, $s['avg_quality_7d']);

        Sanctum::actingAs(User::factory()->create(['anonymous_id' => 'stranger']));
        $this->assertSame([], $this->getJson('/api/v1/sleep/logs')->json('data.entries'));
    }
}
