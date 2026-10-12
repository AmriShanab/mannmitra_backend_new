<?php

namespace Tests\Feature;

use App\Ai\AiClient;
use App\Domain\Exercises\ExerciseRecommender;
use App\Interfaces\PaymentGatewayInterface;
use App\Models\ExerciseRun;
use App\Models\GratitudeEntry;
use App\Models\User;
use App\Services\AIService;
use App\Services\AiCompanionService;
use App\Services\ExerciseAccessService;
use Database\Seeders\ActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeAiClient;
use Tests\TestCase;

class GratitudeNoChatAi extends AIService
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

class GratitudeJournalTest extends TestCase
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
        config(['openai.exercise_ai.enabled' => false]); // each test opts in to AI

        $this->app->bind(AiClient::class, FakeAiClient::class);
        $this->app->bind(AIService::class, GratitudeNoChatAi::class);
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

        $order = $this->access->createOrder($this->user, 'gratitude_01');
        $this->access->confirmPurchase($this->user, $order['order_id'], 'pay_1', 'sig');
    }

    private function start(): array
    {
        return $this->svc->processInteraction($this->user, 'init_cbt', 'gratitude_01');
    }

    private function say(string $text): array
    {
        return $this->svc->processInteraction($this->user, 'text_input', $text);
    }

    /** Runs the three answers and returns the final node. */
    private function finish(array $items = ['Sunny morning walk', 'Chai with a friend', 'Finished my report']): array
    {
        $this->start();
        foreach ($items as $i) {
            $r = $this->say($i);
        }
        return $r;
    }

    private function seedEntry(int $daysAgo, array $items): void
    {
        GratitudeEntry::create(['user_id' => $this->user->id, 'entry_date' => now()->subDays($daysAgo)->toDateString(), 'items' => $items]);
    }

    // ------------------------------------------------------------------ without AI

    public function test_three_good_things_flow_with_no_ai_calls_and_consumes_one_session_at_the_end(): void
    {
        $r = $this->start();
        $this->assertSame('text_input', $r['ui_mode']);
        $this->assertStringContainsString('one good thing', $r['ai_message']);
        $this->assertSame(3, $r['exercise']['total_steps']);

        $r = $this->say('Sunny morning walk');
        $this->assertStringContainsString("That's lovely.", $r['ai_message']);
        $this->assertStringContainsString('second good thing', $r['ai_message']);
        $this->assertSame(5, $this->access->sessionsLeft($this->user, 'gratitude_01'), 'not consumed mid-exercise');

        $r = $this->say('Chai with a friend');
        $this->assertStringContainsString('third thing', $r['ai_message']);

        $r = $this->say('Finished my report');
        $this->assertSame('emoji_slider', $r['ui_mode']);
        $this->assertTrue($r['exercise_completed']);
        $this->assertSame(4, $r['sessions_left']);
        $this->assertSame([], FakeAiClient::$calls, 'no AI with the switch off');
    }

    public function test_first_ever_entry_promises_to_show_it_back_later(): void
    {
        $r = $this->finish();

        $this->assertStringContainsString("I'll show them back to you later", $r['ai_message']);
        $this->assertStringNotContainsString('Here\'s what you were grateful for', $r['ai_message']);
        $this->assertStringContainsString('How are you feeling now?', $r['ai_message']);
    }

    public function test_the_three_items_are_saved_encrypted_and_listed_for_their_owner_only(): void
    {
        $this->finish(['Sunny morning walk', 'Chai with a friend', 'Finished my report']);

        $raw = DB::table('gratitude_entries')->first();
        $this->assertStringNotContainsString('Chai', $raw->items, 'stored encrypted');
        $this->assertSame(now()->toDateString(), substr($raw->entry_date, 0, 10));
        $this->assertSame(['Sunny morning walk', 'Chai with a friend', 'Finished my report'], GratitudeEntry::first()->items);

        Sanctum::actingAs($this->user);
        $rows = $this->getJson('/api/v1/cbt/gratitude')->assertOk()->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame('Chai with a friend', $rows[0]['items'][1]);

        Sanctum::actingAs(User::factory()->create(['anonymous_id' => 'other']));
        $this->assertCount(0, $this->getJson('/api/v1/cbt/gratitude')->json('data'));
    }

    public function test_it_shows_back_last_weeks_entry(): void
    {
        $this->seedEntry(7, ['My sister called', 'A good book', 'Fresh bread']);

        $r = $this->finish();

        $this->assertStringContainsString("Here's what you were grateful for last week:", $r['ai_message']);
        $this->assertStringContainsString("• My sister called\n• A good book\n• Fresh bread", $r['ai_message']);
    }

    public function test_it_prefers_the_entry_closest_to_a_week_over_a_more_recent_one(): void
    {
        $this->seedEntry(2, ['Two days ago thing', 'b', 'c']);
        $this->seedEntry(8, ['Eight days ago thing', 'b', 'c']);

        $r = $this->finish();

        $this->assertStringContainsString('last week', $r['ai_message']);
        $this->assertStringContainsString('Eight days ago thing', $r['ai_message']);
        $this->assertStringNotContainsString('Two days ago thing', $r['ai_message']);
    }

    public function test_without_a_week_old_entry_it_shows_the_most_recent_one_with_an_honest_label(): void
    {
        $this->seedEntry(2, ['Two days ago thing', 'b', 'c']);

        $r = $this->finish();

        $this->assertStringContainsString('2 days ago', $r['ai_message']);
        $this->assertStringContainsString('Two days ago thing', $r['ai_message']);
    }

    public function test_todays_own_entry_is_never_shown_back(): void
    {
        $this->seedEntry(0, ['Already logged today', 'b', 'c']);

        $r = $this->finish();

        $this->assertStringNotContainsString('Already logged today', $r['ai_message']);
        $this->assertStringContainsString("show them back to you later", $r['ai_message']);
    }

    public function test_short_answers_do_not_advance_or_get_saved(): void
    {
        $this->start();

        $r = $this->say('.');

        $this->assertStringContainsString('Take your time', $r['ai_message']);
        $this->assertStringContainsString('one good thing', $r['ai_message']);
        $this->assertSame(0, ExerciseRun::first()->step);
        $this->assertSame(0, GratitudeEntry::count());
    }

    public function test_unfinished_session_saves_nothing_and_costs_nothing(): void
    {
        $this->start();
        $this->say('Sunny morning walk');
        $this->say('Chai with a friend');

        $this->assertSame(0, GratitudeEntry::count());
        $this->assertSame(5, $this->access->sessionsLeft($this->user, 'gratitude_01'));
    }

    public function test_hinglish_for_hindi_users(): void
    {
        $this->user->update(['language' => 'hi']);
        $this->seedEntry(7, ['Behen ka phone', 'Achhi kitaab', 'Taaza roti']);

        $r = $this->start();
        $this->assertStringContainsString('shukr-guzaar', $r['ai_message']);

        $this->say('Subah ki sair');
        $this->say('Dost ke saath chai');
        $r = $this->say('Report poori ho gayi');

        $this->assertStringContainsString('pichhle hafte', $r['ai_message']);
        $this->assertStringContainsString('• Behen ka phone', $r['ai_message']);
    }

    // ------------------------------------------------------------------ with AI

    public function test_with_ai_each_answer_gets_a_reaction_and_the_ending_connects_to_last_week(): void
    {
        config(['openai.exercise_ai.enabled' => true]);
        $this->seedEntry(7, ['My sister called', 'A good book', 'Fresh bread']);

        $this->start();
        $r = $this->say('Sunny morning walk');
        $this->assertStringStartsWith('AI:exercise.voice.gratitude_react', $r['ai_message']);
        $this->assertStringContainsString('second good thing', $r['ai_message'], 'the next question still comes from the app');
        $this->assertStringContainsString('Sunny morning walk', FakeAiClient::$calls[0]['system'], 'the model is told what they wrote');

        $this->say('Chai with a friend');
        $r = $this->say('Finished my report');

        $this->assertTrue($r['exercise_completed']);
        $this->assertStringStartsWith('AI:exercise.voice.gratitude_close', $r['ai_message']);
        $this->assertStringContainsString("• My sister called", $r['ai_message'], 'the look-back list is still the user\'s own words');
        $closeCall = end(FakeAiClient::$calls);
        $this->assertStringContainsString('Finished my report', $closeCall['system']);
        $this->assertStringContainsString('last week', $closeCall['system']);
        $this->assertStringContainsString('My sister called', $closeCall['system']);
    }

    public function test_with_ai_down_it_falls_back_to_fixed_lines_and_still_saves(): void
    {
        config(['openai.exercise_ai.enabled' => true]);
        FakeAiClient::$fail = true;

        $this->start();
        $r = $this->say('Sunny morning walk');
        $this->assertStringContainsString("That's lovely.", $r['ai_message']);

        $this->say('Chai with a friend');
        $r = $this->say('Finished my report');

        $this->assertTrue($r['exercise_completed']);
        $this->assertStringContainsString('Noticing the good takes practice', $r['ai_message']);
        $this->assertSame(1, GratitudeEntry::count());
        $this->assertCount(1, FakeAiClient::$calls, 'one failure pauses the AI for the rest of the session');
    }

    public function test_a_crisis_phrase_during_the_exercise_still_gets_crisis_support(): void
    {
        $this->start();

        $r = $this->say('I want to die');

        $this->assertSame('crisis_cards', $r['ui_mode']);
    }

    // ------------------------------------------------------------------ catalog

    public function test_it_is_in_the_catalog_translated_and_priced(): void
    {
        $catalog = array_column(app(\App\Services\ActivityService::class)->getAllActivities($this->user, 'hi'), null, 'activity_id');

        $this->assertSame('आभार डायरी', $catalog['gratitude_01']['title']);
        $this->assertSame(75, $catalog['gratitude_01']['price']);
        $this->assertSame(5, $catalog['gratitude_01']['sessions_left']);
    }

    public function test_early_evening_recommends_it_when_not_done_today(): void
    {
        $this->travelTo(now()->setTime(18, 30));
        $ids = ['reframing_01', 'breathing_01', 'grounding_01', 'butterfly_hug_01', 'gratitude_01'];

        $this->assertSame('gratitude_01', app(ExerciseRecommender::class)->pick($this->user, $ids));

        ExerciseRun::create(['user_id' => $this->user->id, 'activity_id' => 'gratitude_01', 'status' => 'completed', 'completed_at' => now(), 'state' => []]);
        $this->assertNotSame('gratitude_01', app(ExerciseRecommender::class)->pick($this->user, $ids));
    }
}
