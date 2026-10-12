<?php

namespace Tests\Feature;

use App\Ai\AiUsageReport;
use App\Jobs\GenerateJournalEntryJob;
use App\Models\AiUsageLog;
use App\Models\Activities;
use App\Models\ExerciseRun;
use App\Models\JournalEntry;
use App\Models\Message;
use App\Models\MoodEntry;
use App\Models\Session;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\AIService;
use App\Services\AiCompanionService;
use Database\Seeders\ActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CountingAi extends AIService
{
    public static int $calls = 0;

    public function __construct()
    {
    }

    public function getChatCompletion($systemPrompt, $userInstruction, string $feature = 'companion.chat')
    {
        self::$calls++;
        return $feature === 'journal.daily' || $feature === 'journal.weekly'
            ? ['journal_summary' => 'Today I felt calm.']
            : ['ai_message' => 'ok', 'ui_mode' => 'text_input', 'options' => []];
    }

    public function getUserIntentUsingMini($prompt, $userInstruction, string $feature = 'companion.triage')
    {
        self::$calls++;
        return ['intent' => 'venting_sad'];
    }
}

class ScaleAndPersonalisationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        CountingAi::$calls = 0;
        Cache::flush();
        $this->app->bind(AIService::class, CountingAi::class);
        (new ActivitySeeder)->run();
        $this->user = User::factory()->create(['language' => 'en', 'anonymous_id' => 'anon-1']);
    }

    private function chat(string $text = 'I feel stressed'): array
    {
        return app(AiCompanionService::class)->processInteraction($this->user, 'text_input', $text);
    }

    // ------------------------------------------------------------ AI budget

    public function test_free_user_is_stopped_at_the_daily_limit_without_another_ai_call(): void
    {
        config(['openai.daily_turns.free' => 2]);

        $this->chat();
        $this->chat();
        $callsBefore = CountingAi::$calls;
        $r = $this->chat();

        $this->assertSame('budget_exhausted', $r['node_id']);
        $this->assertStringContainsString('9152987821', $r['ai_message'], 'helpline stays visible');
        $this->assertSame($callsBefore, CountingAi::$calls, 'no AI call once the budget is spent');
    }

    public function test_subscribers_get_the_higher_limit_and_zero_disables_the_cap(): void
    {
        config(['openai.daily_turns.free' => 1, 'openai.daily_turns.subscriber' => 3]);
        Subscription::create([
            'user_id' => $this->user->id, 'plan_type' => 'monthly', 'transaction_id' => 't',
            'razorpay_order_id' => 'o', 'amount' => 99, 'starts_at' => now(),
            'expires_at' => now()->addMonth(), 'status' => 'active',
        ]);

        foreach (range(1, 3) as $_) {
            $this->assertNotSame('budget_exhausted', $this->chat()['node_id']);
        }
        $this->assertSame('budget_exhausted', $this->chat()['node_id']);

        config(['openai.daily_turns.subscriber' => 0]);
        $this->assertNotSame('budget_exhausted', $this->chat()['node_id']);
    }

    public function test_crisis_keywords_are_never_blocked_by_the_budget(): void
    {
        config(['openai.daily_turns.free' => 1]);
        $this->chat();
        $this->chat();

        $r = $this->chat('I want to die');

        $this->assertSame('crisis_cards', $r['ui_mode']);
    }

    // ------------------------------------------------------- recommendation / catalog

    private function catalog(?string $lang = null): array
    {
        $rows = app(ActivityService::class)->getAllActivities($this->user, $lang);
        return array_column($rows, null, 'activity_id');
    }

    public function test_low_mood_recommends_breathing_then_grounding_once_breathing_is_done_today(): void
    {
        MoodEntry::create(['user_id' => $this->user->id, 'primary_mood' => 2, 'note' => 'x']);

        $this->assertTrue($this->catalog()['breathing_01']['recommended']);
        $this->assertFalse($this->catalog()['grounding_01']['recommended']);

        ExerciseRun::create([
            'user_id' => $this->user->id, 'activity_id' => 'breathing_01',
            'status' => 'completed', 'completed_at' => now(), 'state' => [],
        ]);

        $this->assertTrue($this->catalog()['grounding_01']['recommended']);
    }

    public function test_without_signals_the_least_recently_done_exercise_is_recommended(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        foreach (['breathing_01' => 1, 'grounding_01' => 5] as $id => $daysAgo) {
            ExerciseRun::create([
                'user_id' => $this->user->id, 'activity_id' => $id,
                'status' => 'completed', 'completed_at' => now()->subDays($daysAgo), 'state' => [],
            ]);
        }

        // reframing was never done, so it comes first.
        $this->assertTrue($this->catalog()['reframing_01']['recommended']);
        $this->assertCount(1, array_filter($this->catalog(), fn($a) => $a['recommended']));
    }

    public function test_catalog_is_translated_cached_and_hides_raw_translations(): void
    {
        $hi = $this->catalog('hi')['breathing_01'];
        $this->assertSame('साँस की एक्सरसाइज़', $hi['title']);
        $this->assertArrayNotHasKey('translations', $hi);
        $this->assertSame('Breathing', $this->catalog('en')['breathing_01']['title']);

        $this->assertTrue(Cache::has(ActivityService::CACHE_KEY));
        Activities::query()->delete();
        $this->assertCount(5, $this->catalog(), 'served from cache after the table changes');

        (new ActivitySeeder)->run(); // reseeding clears the cache
        $this->assertFalse(Cache::has(ActivityService::CACHE_KEY));
    }

    // ------------------------------------------------------------ queued journals

    private function userWithMessage(string $text): void
    {
        $session = Session::create(['user_id' => $this->user->id, 'status' => 'active', 'type' => 'text']);
        Message::create(['session_id' => $session->id, 'sender' => 'user', 'type' => 'text', 'content' => $text]);
    }

    public function test_commands_queue_one_job_per_active_user_instead_of_calling_ai_inline(): void
    {
        Queue::fake();
        $this->userWithMessage('I had a long day at work today');
        User::factory()->create(['anonymous_id' => 'idle']); // no messages

        $this->artisan('companion:generate-daily-journals')->assertExitCode(0);
        $this->artisan('companion:generate-weekly-journals')->assertExitCode(0);

        Queue::assertPushed(GenerateJournalEntryJob::class, 2);
        Queue::assertPushed(GenerateJournalEntryJob::class, fn($j) => $j->period === 'daily' && $j->user->is($this->user));
        $this->assertSame(0, CountingAi::$calls);
    }

    public function test_daily_job_writes_the_entry_and_weekly_job_skips_thin_data(): void
    {
        $this->userWithMessage('Today was tiring but I enjoyed lunch with a friend.');
        MoodEntry::create(['user_id' => $this->user->id, 'primary_mood' => 6, 'note' => 'x']);

        GenerateJournalEntryJob::dispatchSync($this->user, 'daily', now()->toDateString());
        $entry = JournalEntry::first();
        $this->assertSame('Daily Reflection', $entry->title);
        $this->assertSame(6, (int) $entry->mood_snapshot);

        JournalEntry::query()->delete();
        Message::query()->delete();
        $this->userWithMessage('Just a short note');
        CountingAi::$calls = 0;
        GenerateJournalEntryJob::dispatchSync($this->user, 'weekly', now()->toDateString());
        $this->assertSame(0, JournalEntry::count(), 'fewer than 50 characters: skipped');
        $this->assertSame(0, CountingAi::$calls, 'and no AI spend on it');
    }

    // ------------------------------------------------------ usage report / indexes

    public function test_usage_report_aggregates_by_feature_and_estimates_cost(): void
    {
        AiUsageLog::create(['feature' => 'companion.chat', 'model' => 'gpt-4o', 'prompt_tokens' => 1_000_000, 'completion_tokens' => 100_000, 'latency_ms' => 800, 'status' => 'ok']);
        AiUsageLog::create(['feature' => 'companion.chat', 'model' => 'gpt-4o', 'prompt_tokens' => 0, 'completion_tokens' => 0, 'latency_ms' => 200, 'status' => 'error']);
        AiUsageLog::create(['feature' => 'companion.triage', 'model' => 'gpt-4o-mini', 'prompt_tokens' => 1_000_000, 'completion_tokens' => 0, 'latency_ms' => 100, 'status' => 'ok']);

        $report = app(AiUsageReport::class)->build(7);

        $this->assertSame(3, $report['total_calls']);
        $this->assertSame(1, $report['total_errors']);
        // gpt-4o: 1M*2.50 + 0.1M*10 = 3.50; mini: 0.15
        $this->assertEqualsWithDelta(3.65, $report['est_cost_usd'], 0.001);
        $this->assertSame('companion.chat', $report['by_feature'][0]['feature'], 'most expensive first');
        $this->artisan('ai:usage-report', ['--days' => 7])->assertExitCode(0);
    }

    public function test_performance_indexes_exist(): void
    {
        $this->assertTrue(Schema::hasIndex('messages', 'messages_session_created_idx'));
        $this->assertTrue(Schema::hasIndex('mood_entries', 'mood_entries_user_created_idx'));
        $this->assertTrue(Schema::hasIndex('session', 'session_user_status_idx'));
    }
}
