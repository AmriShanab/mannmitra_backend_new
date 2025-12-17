<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-wellness-reminders')->dailyAt('20:00');
Schedule::command('app:generate-weekly-reflections')->weeklyOn(3, '12:03')->timezone('Asia/Kolkata');
