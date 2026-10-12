<?php

namespace Tests\Feature;

use App\Models\ExerciseCredit;
use App\Models\User;
use App\Services\AIService;
use Database\Seeders\ActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NoAiForBreathing extends AIService
{
    public function __construct()
    {
    }

    public function getChatCompletion($systemPrompt, $userInstruction, string $feature = 'companion.chat')
    {
        throw new \LogicException('no AI');
    }

    public function getUserIntentUsingMini($prompt, $userInstruction, string $feature = 'companion.triage')
    {
        throw new \LogicException('no AI');
    }
}

/** Drives breathing through the real HTTP endpoint, exactly as the app does. */
class BreathingHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_breath_step_in_the_api_response_increases_on_every_tap(): void
    {
        $this->app->bind(AIService::class, NoAiForBreathing::class);
        (new ActivitySeeder)->run();
        $user = User::factory()->create(['language' => 'en', 'anonymous_id' => 'a']);
        ExerciseCredit::create(['user_id' => $user->id, 'activity_id' => 'breathing_01', 'razorpay_order_id' => 'o1',
            'amount' => 75, 'sessions_total' => 5, 'sessions_used' => 0]);
        Sanctum::actingAs($user);

        $post = fn(string $type, string $value) => $this->postJson('/api/v1/companion/interact', ['input_type' => $type, 'input_value' => $value])
            ->assertOk()->json('data');

        $intro = $post('init_cbt', 'breathing_01');
        $this->assertSame(0, $intro['exercise']['step']);

        $steps = [];
        foreach (range(1, 3) as $_) {
            $node = $post('buttons', 'cbt_ready_breathing_01');
            $steps[] = $node['exercise']['step'];
            $this->assertSame('breathing_animation', $node['ui_mode']);
        }

        $this->assertSame([1, 2, 3], $steps);
        $this->assertSame(3, $node['exercise']['total_steps']);
    }
}
