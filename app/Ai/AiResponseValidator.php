<?php

namespace App\Ai;

/**
 * Normalises model JSON into a node the Flutter renderer can always draw:
 * non-empty message, a known ui_mode, and well-formed {id,label} options.
 */
class AiResponseValidator
{
    public const UI_MODES = [
        'buttons',
        'emoji_slider',
        'voice_record',
        'text_input',
        'breathing_animation',
        'crisis_cards',
    ];

    public function node(?array $data, string $fallbackMessage = 'I am here for you.', string $fallbackMode = 'text_input'): array
    {
        $data = $data ?? [];

        $message = $data['ai_message'] ?? null;
        $message = (is_string($message) && trim($message) !== '') ? trim($message) : $fallbackMessage;

        $mode = $data['ui_mode'] ?? null;
        if (!is_string($mode) || !in_array($mode, self::UI_MODES, true)) {
            $mode = $fallbackMode;
        }

        $options = [];
        foreach ((array) ($data['options'] ?? []) as $opt) {
            if (is_array($opt) && isset($opt['id'], $opt['label']) && is_scalar($opt['id']) && is_scalar($opt['label'])) {
                $options[] = ['id' => (string) $opt['id'], 'label' => (string) $opt['label']];
            }
        }

        // A button/breathing screen with nothing to tap would strand the user.
        if (in_array($mode, ['buttons', 'breathing_animation'], true) && $options === []) {
            $mode = $fallbackMode;
        }

        return ['ai_message' => $message, 'ui_mode' => $mode, 'options' => $options];
    }

    /**
     * Returns the intent only if it is one the router knows; otherwise null.
     */
    public function intent(?array $data, array $allowed): ?string
    {
        $intent = $data['intent'] ?? null;
        return (is_string($intent) && in_array($intent, $allowed, true)) ? $intent : null;
    }
}
