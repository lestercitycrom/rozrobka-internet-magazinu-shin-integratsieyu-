<?php

use App\Jobs\RecordQueueHeartbeat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('easytire:outbox --limit=25')->everyMinute()->withoutOverlapping();
Schedule::command('ndi:media:sync --limit=25')
    ->hourly()
    ->when(fn (): bool => (bool) config('ndi.media_sync_enabled', false))
    ->withoutOverlapping();
Schedule::command('ndi:inbox:sync --limit=20')
    ->everyTenMinutes()
    ->when(fn (): bool => filled(config('ndi.inbox_path')))
    ->withoutOverlapping();
Schedule::command('ndi:sandbox-catalog')
    ->hourly()
    ->when(fn (): bool => ! app()->environment('production') && (bool) config('ndi_api.sandbox.enabled'))
    ->withoutOverlapping();
Schedule::call(fn () => Cache::forever('easytire.health.scheduler_heartbeat_at', now()->toIso8601String()))
    ->name('health:scheduler-heartbeat')
    ->everyMinute()
    ->withoutOverlapping();
Schedule::job(new RecordQueueHeartbeat)
    ->name('health:queue-heartbeat')
    ->everyMinute()
    ->withoutOverlapping();
