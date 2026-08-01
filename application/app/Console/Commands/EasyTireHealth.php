<?php

namespace App\Console\Commands;

use App\Modules\Operations\SystemHealth;
use Illuminate\Console\Command;

final class EasyTireHealth extends Command
{
    protected $signature = 'easytire:health';

    protected $description = 'Report redacted operational health.';

    public function handle(SystemHealth $health): int
    {
        $r = $health->report();
        $this->line('db='.($r['database'] ? 'ok' : 'fail').' queue='.$r['queue'].' cache='.$r['cache'].' failed_jobs='.($r['failed_jobs'] ?? 'unknown').' ndi_age_minutes='.($r['ndi_age_minutes'] ?? 'none').' scheduler='.($r['scheduler_healthy'] ? 'ok' : 'stale').' worker='.($r['queue_healthy'] ? 'ok' : 'stale').' disk='.($r['disk_ok'] ? 'ok' : 'warning'));

        return $health->healthy() ? self::SUCCESS : self::FAILURE;
    }
}
