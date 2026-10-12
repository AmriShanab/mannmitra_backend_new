<?php

namespace Tests\Unit;

use App\Ai\PromptRepository;
use Tests\TestCase;

class PromptRepositoryTest extends TestCase
{
    public function test_every_prompt_template_renders_without_leftover_placeholders(): void
    {
        $vars = [
            'language' => 'English',
            'recent_messages' => 'User: hi',
            'recent_moods' => 'No recent mood data.',
            'sense_verb' => 'see',
            'exercise' => 'Breathing',
            'task' => 'Do a thing.',
            'length' => 'one sentence',
            'context' => 'Mood unknown.',
        ];

        $files = glob(resource_path('prompts/*.txt'));
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $out = app(PromptRepository::class)->render(basename($file, '.txt'), $vars);
            $this->assertStringNotContainsString('{{', $out, basename($file) . ' has an unfilled placeholder');
            $this->assertNotSame('', trim($out));
        }
    }

    public function test_unknown_prompt_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(PromptRepository::class)->render('nope');
    }
}
