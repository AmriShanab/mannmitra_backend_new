<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-wellness-reminders')->dailyAt('13:13')->timezone('Asia/Kolkata');
Schedule::command('app:generate-weekly-reflections')->weeklyOn(0, '18:00')->timezone('Asia/Kolkata');
