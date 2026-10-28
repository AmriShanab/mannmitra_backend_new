<?php

// Push / in-app notification text. Fixed copy for scheduled push notifications (no AI involved).
return [
    'reminder_title' => 'Your session starts in 30 minutes ⏰',
    'reminder_body' => 'Your session starts at :time. Open the app to join.',
    'reminder_body_doctor' => 'Your session with Dr. :doctor starts at :time. Open the app to join.',
    'mood' => [
        ['title' => 'Hi there!', 'body' => 'You haven\'t logged your mood today. How are you feeling?'],
        ['title' => 'Checking in 💙', 'body' => 'Take a moment for yourself. How is your day going?'],
    ],
    'crisis_followup' => [
        ['title' => 'Thinking of you 💙', 'body' => 'You were feeling a bit low a couple of days ago. Are you doing any better today?'],
        ['title' => 'Checking in', 'body' => 'We\'re here for you. Take a deep breath and let us know how today is going.'],
    ],
    'reengagement' => [
        ['title' => 'We miss you! 💙', 'body' => 'It\'s been a week since we last chatted. How have you been?'],
        ['title' => 'Thinking of you', 'body' => 'Your AI companion is here whenever you\'re ready to talk. Come say hi!'],
    ],
];
