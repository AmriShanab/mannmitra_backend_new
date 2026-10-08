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

    'razorpay' => [
        'key' => env('RAZORPAY_API_KEY'),
        'secret' => env('RAZORPAY_API_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    // Prices in INR. Server-side source of truth; never trust client amounts.
    'pricing' => [
        'ticket' => 99,
        'appointment' => 499,
        'plans' => [
            'monthly' => ['amount' => 99, 'period' => 'month'],
            'yearly' => ['amount' => 799, 'period' => 'year'],
        ],
    ],

    // Chat/call signaling server. REALTIME_SECRET must match the socket server's env.
    'realtime' => [
        'url' => env('REALTIME_SOCKET_URL', 'http://31.97.232.145:3000'),
        'secret' => env('REALTIME_SECRET'),
    ],

    // TURN relay. With TURN_SECRET (coturn use-auth-secret) credentials are time-limited.
    'turn' => [
        'urls' => env('TURN_URLS', 'turn:31.97.232.145:3478'),
        'secret' => env('TURN_SECRET'),
        'username' => env('TURN_USERNAME'),
        'password' => env('TURN_PASSWORD'),
    ],

];
