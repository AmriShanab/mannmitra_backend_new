<?php

namespace Tests\Feature;

use App\Ai\AiClient;
use App\Domain\Exercises\ExerciseSessionService;
use App\Models\User;
use App\Services\AIService;
use App\Services\ActivityService;
use App\Services\AiCompanionService;
use App\Support\Language;
use Database\Seeders\ActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeAiClient;
use Tests\TestCase;

class LanguageNoChatAi extends AIService
{
    public static array $prompts = [];

    public function __construct()
    {
    }

    public function getChatCompletion($systemPrompt, $userInstruction, string $feature = 'companion.chat')
    {
        self::$prompts[] = $systemPrompt;
        throw new \RuntimeException('offline'); // forces the fixed fallback text
    }

    public function getUserIntentUsingMini($prompt, $userInstruction, string $feature = 'companion.triage')
    {
        return ['intent' => 'venting_sad'];
    }
}

class LanguageSupportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        FakeAiClient::reset();
        LanguageNoChatAi::$prompts = [];
        $this->app->bind(AiClient::class, FakeAiClient::class);
        $this->app->bind(AIService::class, LanguageNoChatAi::class);
        (new ActivitySeeder)->run();
    }

    // ------------------------------------------------------------------ the Language helper

    public function test_codes_are_normalised_and_iso_aliases_are_accepted(): void
    {
        $this->assertSame('be', Language::normalize('bn'));
        $this->assertSame('bh', Language::normalize('BHO'));
        $this->assertSame('ta', Language::normalize(' ta '));
        $this->assertSame('en', Language::normalize('xx'));
        $this->assertSame('en', Language::normalize(null));
        $this->assertTrue(Language::isSupported('bn'));
        $this->assertFalse(Language::isSupported('fr'));
    }

    public function test_each_language_gets_the_right_ai_instruction_speech_code_and_script_set(): void
    {
        $this->assertSame('conversational Hinglish (Latin script)', Language::promptName('hi'), 'Hindi stays Hinglish');
        $this->assertStringStartsWith('Tamil', Language::promptName('ta'));
        $this->assertStringStartsWith('Bengali', Language::promptName('be'));
        $this->assertStringStartsWith('Bhojpuri', Language::promptName('bh'));
        $this->assertSame('English', Language::promptName('fr'));

        // Bengali is 'be' in the app but 'bn' for speech; Bhojpuri has no code there, so Hindi.
        $this->assertSame('bn', Language::speechCode('be'));
        $this->assertSame('hi', Language::speechCode('bh'));
        $this->assertSame('mr', Language::speechCode('mr'));

        // Exercise scripts exist in English and Hinglish only.
        $this->assertSame('hi', Language::exerciseLang('bh'));
        $this->assertSame('en', Language::exerciseLang('ta'));
        $this->assertSame('hi', Language::exerciseLang('hi'));
    }

    public function test_every_language_has_every_server_message(): void
    {
        foreach (['companion', 'push'] as $file) {
            $en = array_keys(require lang_path("en/$file.php"));
            foreach (Language::SUPPORTED as $code) {
                $keys = array_keys(require lang_path("$code/$file.php"));
                $this->assertSame($en, $keys, "$code/$file.php keys differ from English");
            }
        }
    }

    public function test_fixed_messages_and_notification_pools_come_in_the_users_language(): void
    {
        $this->assertStringContainsString('மன மித்ரா', Language::text('companion.welcome', 'ta', ['name' => 'Asha']));
        $this->assertStringContainsString('Asha', Language::text('companion.welcome', 'ta', ['name' => 'Asha']));
        $this->assertStringContainsString('₹75', Language::text('companion.payment_required', 'mr', ['sessions' => 5, 'amount' => 75]));
        $this->assertStringContainsString('Main yahin', Language::text('companion.connection_slow', 'hi'));

        $pool = Language::lines('push.mood', 'te');
        $this->assertCount(2, $pool);
        $this->assertSame('నమస్తే!', $pool[0]['title']);
        $this->assertSame('Missing key', Language::text('Missing key', 'ta'), 'an unknown key never crashes');
    }

    // ------------------------------------------------------------------ the API

    public function test_the_language_endpoint_accepts_every_app_language_and_rejects_others(): void
    {
        $user = User::factory()->create(['anonymous_id' => 'anon-l', 'language' => 'en']);
        Sanctum::actingAs($user);

        foreach (['hi', 'mr', 'ta', 'te', 'be', 'bh', 'en'] as $code) {
            $this->putJson('/api/v1/language', ['languageCode' => $code])->assertOk();
            $this->assertSame($code, $user->fresh()->language);
        }

        $this->putJson('/api/v1/language', ['languageCode' => 'bn'])->assertOk();
        $this->assertSame('be', $user->fresh()->language, 'ISO code stored as the app code');

        $this->putJson('/api/v1/language', ['languageCode' => 'fr'])->assertStatus(422);
        $this->assertSame('be', $user->fresh()->language);
    }

    public function test_the_activity_catalog_is_translated_for_every_language(): void
    {
        $user = User::factory()->create(['anonymous_id' => 'anon-c']);
        $svc = app(ActivityService::class);

        $expect = ['mr' => 'श्वासाचा व्यायाम', 'ta' => 'மூச்சுப் பயிற்சி', 'te' => 'శ్వాస వ్యాయామం', 'be' => 'শ্বাসের ব্যায়াম', 'bh' => 'साँस के अभ्यास'];
        foreach ($expect as $code => $title) {
            $catalog = array_column($svc->getAllActivities($user, $code), null, 'activity_id');
            $this->assertSame($title, $catalog['breathing_01']['title'], $code);
            $this->assertCount(7, $catalog);
        }
    }

    // ------------------------------------------------------------------ the AI companion

    public function test_the_payment_message_and_ai_fallback_are_in_the_users_language(): void
    {
        $user = User::factory()->create(['anonymous_id' => 'anon-p', 'language' => 'mr']);
        $svc = app(AiCompanionService::class);

        $r = $svc->processInteraction($user, 'init_cbt', 'breathing_01');
        $this->assertSame('payment_required', $r['ui_mode']);
        $this->assertStringContainsString('अनलॉक', $r['ai_message']);

        // AI down: the fixed fallback reply is Marathi, and the prompt asked for Marathi.
        $r = $svc->processInteraction($user, 'text_input', 'Aaj khup thakva aala aahe');
        $this->assertSame('msg_error_fallback', $r['node_id']);
        $this->assertStringContainsString('कनेक्शन', $r['ai_message']);
        $this->assertStringContainsString('Marathi', end(LanguageNoChatAi::$prompts));
    }

    public function test_exercises_use_hinglish_for_bhojpuri_and_english_for_other_languages(): void
    {
        $method = new \ReflectionMethod(ExerciseSessionService::class, 'lang');
        $svc = app(ExerciseSessionService::class);

        $this->assertSame('hi', $method->invoke($svc, new User(['language' => 'bh'])));
        $this->assertSame('en', $method->invoke($svc, new User(['language' => 'te'])));
    }
}
