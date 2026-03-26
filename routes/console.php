<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-wellness-reminders')->dailyAt('11:08')->timezone('Asia/Kolkata');
// Schedule::command('app:generate-weekly-reflections')->weeklyOn(0, '18:00')->timezone('Asia/Kolkata');
Schedule::command('companion:generate-weekly-journals')->weeklyOn(0, '23:59')->timezone('Asia/Kolkata');
Schedule::command('closed:expired:appointments')->daily();
Schedule::command('companion:generate-daily-journals')->dailyAt('18:00');