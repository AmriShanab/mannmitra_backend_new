<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-wellness-reminders')->dailyAt('17:00')->timezone('Asia/Kolkata');
// Schedule::command('app:generate-weekly-reflections')->weeklyOn(0, '18:00')->timezone('Asia/Kolkata');
Schedule::command('companion:generate-weekly-journals')->weeklyOn(0, '23:59')->timezone('Asia/Kolkata');
Schedule::command('closed:expired:appointments')->daily();
Schedule::command('companion:generate-daily-journals')->dailyAt('18:00');
Schedule::command('app:send-crisis-follow-up-reminders')->dailyAt('13:00');

Schedule::command('app:send-reengagement-reminders')->dailyAt('17:00');

Schedule::call(fn () => app(\App\Services\SubscriptionService::class)->expireLapsed())
    ->hourly()
    ->name('subscriptions-expire-lapsed');

// Every minute: remind patients (push) and doctors (dashboard) ~30 min before a confirmed session.
Schedule::command('app:send-appointment-reminders')->everyMinute()->withoutOverlapping();
