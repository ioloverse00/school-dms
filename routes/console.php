<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('notifications:deliver-email')->everyMinute()->withoutOverlapping(10);
Schedule::command('ai:refresh-forecast')->twiceDaily(0, 12)
    ->timezone(config('app.timezone'))->withoutOverlapping(10);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
