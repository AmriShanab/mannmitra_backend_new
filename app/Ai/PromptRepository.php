<?php

namespace App\Ai;

/**
 * Loads prompt templates from resources/prompts/{name}.txt and fills {{placeholders}}.
 * Keeping prompts out of PHP lets them be reviewed and edited without touching logic.
 */
class PromptRepository
{
    public function render(string $name, array $vars = []): string
    {
        $path = resource_path("prompts/$name.txt");
        if (!is_file($path)) {
            throw new \InvalidArgumentException("Prompt template not found: $name");
        }

        $template = file_get_contents($path);
        foreach ($vars as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }

        return $template;
    }

    public static function languageName(?string $code): string
    {
        return $code === 'hi' ? 'conversational Hinglish (Latin script)' : 'English';
    }
}
