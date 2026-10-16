<?php

namespace Tests\Feature;

use App\Ai\AiClient;
use App\Domain\Exercises\ExerciseRecommender;
use App\Interfaces\PaymentGatewayInterface;
use App\Models\User;
use App\Models\UserGoal;
use App\Services\AIService;
use App\Services\AiCompanionService;
use App\Services\ExerciseAccessService;
use App\Services\GoalService;
use Database\Seeders\ActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeAiClient;
use Tests\TestCase;

class GoalNoChatAi extends AIService
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

class ValuesGoalTest extends TestCase
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
        $this->app->bind(AIService::class, GoalNoChatAi::class);
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

        $order = $this->access->createOrder($this->user, 'values_goal_01');
        $this->access->confirmPurchase($this->user, $order['order_id'], 'pay_1', 'sig');
    }

    private function start(): array
    {
        return $this->svc->processInteraction($this->user, 'init_cbt', 'values_goal_01');
    }

    private function choose(string $id): array
    {
        return $this->svc->processInteraction($this->user, 'buttons', $id);
    }

    private function say(string $text): array
    {
        return $this->svc->processInteraction($this->user, 'text_input', $text);
    }

    private function seedGoal(int $daysAgo, string $text = 'Walk for 15 minutes on 3 days'): UserGoal
    {
        $g = UserGoal::create([
            'user_id' => $this->user->id, 'core_values' => ['health'], 'focus_value' => 'health', 'goal_text' => $text,
            'confidence' => 2, 'status' => 'active', 'due_date' => now()->addDays(7 - $daysAgo)->toDateString(),
        ]);
        $g->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

        return $g;
    }

    // ------------------------------------------------------------------ setting a goal

    public function test_full_flow_with_no_ai_saves_a_weekly_goal_and_consumes_one_session_at_the_end(): void
    {
        $r = $this->start();
        $this->assertSame('values_picker', $r['ui_mode']);
        $this->assertCount(9, $r['exercise']['items']);
        $this->assertSame(3, $r['exercise']['max']);
        $this->assertSame('goal_vals:', $r['options'][0]['id']);

        $r = $this->choose('goal_vals:family,health,bogus,fun');
        $this->assertSame('buttons', $r['ui_mode']);
        $this->assertStringContainsString('Which one would you like to focus on', $r['ai_message']);
        $this->assertSame(['goal_focus_family', 'goal_focus_health', 'goal_focus_fun'], array_column($r['options'], 'id'));

        $r = $this->choose('goal_focus_health');
        $this->assertSame(['goal_pick_1', 'goal_pick_2', 'goal_pick_3', 'goal_own'], array_column($r['options'], 'id'));
        $this->assertSame('Walk for 15 minutes on 3 days', $r['options'][0]['label']);

        $r = $this->choose('goal_pick_1');
        $this->assertStringContainsString('How sure are you', $r['ai_message']);
        $this->assertSame(5, $this->access->sessionsLeft($this->user, 'values_goal_01'), 'not consumed before the end');

        $r = $this->choose('goal_conf_3');
        $this->assertSame('emoji_slider', $r['ui_mode']);
        $this->assertTrue($r['exercise_completed']);
        $this->assertSame(4, $r['sessions_left']);
        $this->assertStringContainsString('Walk for 15 minutes on 3 days', $r['ai_message']);
        $this->assertSame([], FakeAiClient::$calls, 'no AI with the switch off');

        $goal = UserGoal::first();
        $this->assertSame('Walk for 15 minutes on 3 days', $goal->goal_text);
        $this->assertSame('health', $goal->focus_value);
        $this->assertSame(['family', 'health', 'fun'], $goal->core_values);
        $this->assertSame(3, $goal->confidence);
        $this->assertSame('active', $goal->status);
        $this->assertTrue($goal->due_date->isSameDay(now()->addDays(7)));
        $this->assertNotSame('Walk for 15 minutes on 3 days', DB::table('user_goals')->value('goal_text'), 'encrypted at rest');
    }

    public function test_one_value_skips_the_focus_step_and_a_typed_goal_is_accepted(): void
    {
        $this->start();
        $r = $this->choose('goal_vals:calm');
        $this->assertStringContainsString('Inner peace', $r['ai_message']);
        $this->assertContains('goal_own', array_column($r['options'], 'id'));

        $r = $this->choose('goal_own');
        $this->assertSame('text_input', $r['ui_mode']);

        $r = $this->say('ok');
        $this->assertSame('text_input', $r['ui_mode'], 'too short, asked again');

        $r = $this->say('Meditate for five minutes after dinner');
        $this->assertStringContainsString('How sure are you', $r['ai_message']);
        $this->choose('goal_conf_2');

        $this->assertSame('Meditate for five minutes after dinner', UserGoal::first()->goal_text);
    }

    public function test_choosing_no_values_asks_again_and_extra_values_are_capped_at_three(): void
    {
        $this->start();
        $r = $this->choose('goal_vals:');
        $this->assertSame('values_picker', $r['ui_mode']);
        $this->assertStringContainsString('at least one value', $r['ai_message']);

        $r = $this->choose('goal_vals:family,health,work,friends,fun');
        $this->assertCount(3, $r['options']);
    }

    public function test_a_low_confidence_goal_is_shrunk_once(): void
    {
        $this->start();
        $this->choose('goal_vals:health');
        $this->choose('goal_pick_1');

        $r = $this->choose('goal_conf_1');
        $this->assertSame('text_input', $r['ui_mode']);
        $this->assertStringContainsString('make it smaller', $r['ai_message']);

        $r = $this->say('Walk for 5 minutes today');
        $this->assertTrue($r['exercise_completed']);
        $this->assertSame('Walk for 5 minutes today', UserGoal::first()->goal_text);
    }

    public function test_a_new_goal_replaces_the_old_one_which_stays_in_history(): void
    {
        $old = $this->seedGoal(7);

        $this->start();
        $this->choose('goal_ci_not');
        $this->choose('goal_new');
        $this->choose('goal_vals:fun');
        $this->choose('goal_pick_3');
        $r = $this->choose('goal_conf_2');

        $this->assertTrue($r['exercise_completed']);
        $this->assertSame('replaced', $old->fresh()->status);
        $this->assertSame('not', $old->fresh()->progress);
        $this->assertSame(1, UserGoal::where('status', 'active')->count());
        $this->assertSame('Plan one outing for the weekend', UserGoal::where('status', 'active')->first()->goal_text);
    }

    // ------------------------------------------------------------------ checking in

    public function test_a_goal_set_before_today_is_checked_in_on_first(): void
    {
        $goal = $this->seedGoal(7);

        $r = $this->start();
        $this->assertSame('buttons', $r['ui_mode']);
        $this->assertStringContainsString('Walk for 15 minutes on 3 days', $r['ai_message']);
        $this->assertSame(['goal_ci_done', 'goal_ci_partly', 'goal_ci_not'], array_column($r['options'], 'id'));

        $r = $this->choose('goal_ci_done');
        $this->assertStringContainsString('Well done', $r['ai_message']);
        $this->assertSame(['goal_new', 'goal_stop'], array_column($r['options'], 'id'));
        $this->assertSame('completed', $goal->fresh()->status, 'saved right away');
        $this->assertSame('done', $goal->fresh()->progress);

        $r = $this->choose('goal_new');
        $this->assertSame('values_picker', $r['ui_mode']);
    }

    public function test_check_in_can_keep_the_same_goal_for_another_week(): void
    {
        $goal = $this->seedGoal(7);
        $this->start();

        $r = $this->choose('goal_ci_not');
        $this->assertSame(['goal_keep', 'goal_new'], array_column($r['options'], 'id'));

        $r = $this->choose('goal_keep');
        $this->assertTrue($r['exercise_completed']);
        $this->assertStringContainsString('keep this goal for another week', $r['ai_message']);

        $goal = $goal->fresh();
        $this->assertSame('active', $goal->status);
        $this->assertNull($goal->progress);
        $this->assertTrue($goal->due_date->isSameDay(now()->addDays(7)));
        $this->assertSame(1, UserGoal::count());
        $this->assertNull(app(GoalService::class)->dueForCheckIn($this->user->id), 'next check-in is a week away');
    }

    public function test_declining_a_new_goal_after_finishing_one_ends_politely(): void
    {
        $this->seedGoal(7);
        $this->start();
        $this->choose('goal_ci_done');

        $r = $this->choose('goal_stop');

        $this->assertTrue($r['exercise_completed']);
        $this->assertStringContainsString('Come back whenever', $r['ai_message']);
    }

    public function test_a_goal_set_today_is_not_checked_in_on_again(): void
    {
        $this->seedGoal(0);

        $r = $this->start();

        $this->assertSame('values_picker', $r['ui_mode']);
    }

    public function test_a_wrong_choice_repeats_the_same_step(): void
    {
        $r = $this->start();
        $r = $this->choose('goal_pick_2');
        $this->assertSame('values_picker', $r['ui_mode']);

        $this->choose('goal_vals:health');
        $this->choose('goal_pick_1');
        $r = $this->choose('goal_conf_9');
        $this->assertStringContainsString('Please choose one', $r['ai_message']);
        $this->assertSame('buttons', $r['ui_mode']);
    }

    // ------------------------------------------------------------------ free screen API

    public function test_the_free_goals_endpoint_lists_the_goal_and_accepts_a_check_in(): void
    {
        $goal = $this->seedGoal(3);
        Sanctum::actingAs($this->user);

        $r = $this->getJson('/api/v1/cbt/goals')->assertOk();
        $this->assertSame('Walk for 15 minutes on 3 days', $r->json('data.active.goal'));
        $this->assertSame('Health', $r->json('data.active.value_label'));
        $this->assertSame(4, $r->json('data.active.days_left'));

        $r = $this->postJson("/api/v1/cbt/goals/{$goal->id}/checkin", ['progress' => 'done'])->assertOk();
        $this->assertNull($r->json('data.active'));
        $this->assertSame('done', $r->json('data.history.0.progress'));

        $this->postJson("/api/v1/cbt/goals/{$goal->id}/checkin", ['progress' => 'done'])->assertStatus(404);
        $this->postJson("/api/v1/cbt/goals/{$goal->id}/checkin", ['progress' => 'wow'])->assertStatus(422);
    }

    public function test_one_user_cannot_check_in_on_anothers_goal(): void
    {
        $goal = $this->seedGoal(3);
        Sanctum::actingAs(User::factory()->create(['anonymous_id' => 'other']));

        $this->postJson("/api/v1/cbt/goals/{$goal->id}/checkin", ['progress' => 'done'])->assertStatus(404);
        $this->assertSame('active', $goal->fresh()->status);
    }

    // ------------------------------------------------------------------ recommendation, hinglish, crisis

    public function test_a_due_goal_makes_this_the_recommended_exercise(): void
    {
        $ids = ['breathing_01', 'values_goal_01'];

        $this->seedGoal(7);

        $this->assertSame('values_goal_01', app(ExerciseRecommender::class)->pick($this->user, $ids));
    }

    public function test_hinglish_text_is_used_for_hindi_users(): void
    {
        $this->user->update(['language' => 'hi']);

        $r = $this->start();
        $this->assertStringContainsString('sabse zaroori', $r['ai_message']);
        $this->assertSame('Parivaar', $r['exercise']['items'][0]['label']);

        $r = $this->choose('goal_vals:health');
        $this->assertSame('3 din 15 minute tehalna', $r['options'][0]['label']);
    }

    public function test_a_crisis_phrase_still_gets_crisis_support(): void
    {
        $this->start();
        $this->choose('goal_vals:health');
        $this->choose('goal_own');

        $r = $this->say('I want to die');

        $this->assertSame('crisis_cards', $r['ui_mode']);
    }

    // ------------------------------------------------------------------ with AI

    public function test_with_ai_the_values_and_the_ending_get_a_personal_line(): void
    {
        config(['openai.exercise_ai.enabled' => true]);

        $this->start();
        $r = $this->choose('goal_vals:family,health');
        $this->assertStringStartsWith('AI:exercise.voice.goal_react', $r['ai_message']);
        $this->assertStringContainsString('Family, Health', FakeAiClient::$calls[0]['system']);
        $this->assertStringContainsString('Which one would you like to focus on', $r['ai_message'], 'the next question still comes from the app');

        $this->choose('goal_focus_health');
        $this->choose('goal_pick_2');
        $r = $this->choose('goal_conf_3');

        $this->assertStringStartsWith('AI:exercise.voice.goal_close', $r['ai_message']);
        $this->assertStringContainsString('Drink enough water on most days', $r['ai_message'], 'the saved goal is still shown verbatim');
        $this->assertStringContainsString('Drink enough water', end(FakeAiClient::$calls)['system']);
    }

    public function test_with_ai_down_it_falls_back_to_fixed_lines_and_still_saves(): void
    {
        config(['openai.exercise_ai.enabled' => true]);
        FakeAiClient::$fail = true;

        $this->start();
        $r = $this->choose('goal_vals:health');
        $this->assertStringContainsString('meaningful choices', $r['ai_message']);

        $this->choose('goal_pick_1');
        $r = $this->choose('goal_conf_3');

        $this->assertTrue($r['exercise_completed']);
        $this->assertStringContainsString('Small steps toward what matters', $r['ai_message']);
        $this->assertSame(1, UserGoal::count());
    }

    public function test_it_is_in_the_catalog_translated_and_priced(): void
    {
        $catalog = array_column(app(\App\Services\ActivityService::class)->getAllActivities($this->user, 'hi'), null, 'activity_id');

        $this->assertArrayHasKey('values_goal_01', $catalog);
        $this->assertSame('मूल्य और लक्ष्य', $catalog['values_goal_01']['title']);
    }
}
