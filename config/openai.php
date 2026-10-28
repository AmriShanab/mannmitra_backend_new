<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key and Organization
    |--------------------------------------------------------------------------
    |
    | Here you may specify your OpenAI API Key and organization. This will be
    | used to authenticate with the OpenAI API - you can find your API key
    | and organization on your OpenAI dashboard, at https://openai.com.
    */

    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Project
    |--------------------------------------------------------------------------
    |
    | Here you may specify your OpenAI API project. This is used optionally in
    | situations where you are using a legacy user API key and need association
    | with a project. This is not required for the newer API keys.
    */
    'project' => env('OPENAI_PROJECT'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Base URL
    |--------------------------------------------------------------------------
    |
    | Here you may specify your OpenAI API base URL used to make requests. This
    | is needed if using a custom API endpoint. Defaults to: api.openai.com/v1
    */
    'base_uri' => env('OPENAI_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout may be used to specify the maximum number of seconds to wait
    | for a response. By default, the client will time out after 30 seconds.
    */

    'request_timeout' => env('OPENAI_REQUEST_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Models, retries and token caps (used by App\Ai\AiClient)
    |--------------------------------------------------------------------------
    | "chat" is the full model for free-form replies; "mini" is for triage,
    | classification and short reactions.
    */
    'models' => [
        'chat' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        'mini' => env('OPENAI_MINI_MODEL', 'gpt-4o-mini'),
        'transcribe' => env('OPENAI_TRANSCRIBE_MODEL', 'gpt-4o-mini-transcribe'),
        'tts' => env('OPENAI_TTS_MODEL', 'tts-1'),
    ],
    'retries' => (int) env('OPENAI_RETRIES', 2),
    'retry_sleep_ms' => (int) env('OPENAI_RETRY_SLEEP_MS', 300),
    // Output cap for any reply that does not set its own. ~250 tokens is roughly 150 words.
    'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 250),
    // Longest text (characters) that is turned into speech.
    'tts_max_chars' => (int) env('OPENAI_TTS_MAX_CHARS', 600),
    // How many past messages go into a chat prompt.
    'history_messages' => (int) env('OPENAI_HISTORY_MESSAGES', 10),

    // AI-backed chat turns per user per day (0 disables the cap). Exercises on the
    // state machine do not count against this.
    'daily_turns' => [
        'free' => (int) env('AI_DAILY_TURNS_FREE', 60),
        'subscriber' => (int) env('AI_DAILY_TURNS_SUBSCRIBER', 300),
    ],

    // Estimated USD per 1M tokens, for the usage report only. Check OpenAI's pricing page and
    // adjust here; unknown models are reported with a cost of 0.
    'prices' => [
        'gpt-4o' => ['in' => 2.50, 'out' => 10.00],
        'gpt-4o-mini' => ['in' => 0.15, 'out' => 0.60],
        'whisper-1' => ['in' => 0.0, 'out' => 0.0],
        'gpt-4o-mini-transcribe' => ['in' => 0.0, 'out' => 0.0],
        'tts-1' => ['in' => 0.0, 'out' => 0.0],
    ],

    // Optional AI wording inside exercises. Off = fixed text only (no OpenAI calls at all).
    // On = short personalised lines that react to what the user typed; any failure falls back
    // to the fixed text, and a failure pauses the AI for `breaker_seconds`.
    'exercise_ai' => [
        'enabled' => (bool) env('EXERCISE_AI_ENABLED', false),
        'timeout' => (int) env('EXERCISE_AI_TIMEOUT', 8),
        'breaker_seconds' => (int) env('EXERCISE_AI_BREAKER_SECONDS', 60),
    ],

    // Server-tracked exercise state machines (breathing, grounding). Set false to fall
    // back to the legacy LLM-driven exercise prompts.
    'exercise_state_machine' => (bool) env('EXERCISES_STATE_MACHINE', true),
];
