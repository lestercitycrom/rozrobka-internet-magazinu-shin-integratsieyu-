<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('easytire:outbox --limit=25')->everyMinute()->withoutOverlapping();
Schedule::command('ndi:media:sync --limit=25')->hourly()->withoutOverlapping();
