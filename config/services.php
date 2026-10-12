<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Read through config() so values survive `php artisan config:cache`.
    'whapi' => [
        'url' => env('WHAPI_URL'),
        'token' => env('WHAPI_TOKEN'),
    ],

    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS', 'storage/app/firebase_credentials.json'),
    ],

    'razorpay' => [
        'key' => env('RAZORPAY_API_KEY'),
        'secret' => env('RAZORPAY_API_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    // Prices in INR. Server-side source of truth; never trust client amounts.
    'pricing' => [
        'ticket' => (int) env('TICKET_PRICE', 99),
        'appointment' => (int) env('APPOINTMENT_PRICE', 499),
        // Temporary: pay once per exercise to unlock a fixed number of completed sessions.
        'exercise' => [
            // Set EXERCISE_PAYWALL_ENABLED=false to make exercises free (e.g. until the app update is live).
            'enabled' => (bool) env('EXERCISE_PAYWALL_ENABLED', true),
            'amount' => (int) env('EXERCISE_PRICE', 75),
            'sessions' => (int) env('EXERCISE_SESSIONS', 5),
        ],
        'plans' => [
            'monthly' => ['amount' => 99, 'period' => 'month'],
            'yearly' => ['amount' => 799, 'period' => 'year'],
        ],
    ],

    // Chat/call signaling server. REALTIME_SECRET must match the socket server's env.
    'realtime' => [
        'url' => env('REALTIME_SOCKET_URL', 'https://mann-mitra.co.in'),
        'secret' => env('REALTIME_SECRET'),
    ],

    // TURN relay. With TURN_SECRET (coturn use-auth-secret) credentials are time-limited.
    'turn' => [
        'urls' => env('TURN_URLS', 'turn:mann-mitra.co.in:3478'),
        'secret' => env('TURN_SECRET'),
        'username' => env('TURN_USERNAME'),
        'password' => env('TURN_PASSWORD'),
    ],

];
