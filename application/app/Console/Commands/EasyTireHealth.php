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
        $ndiStock = $r['ndi_age_minutes'] === null
            ? ($r['ndi_live_catalog_published'] ? 'missing' : 'not-required')
            : ($r['ndi_fresh'] ? 'fresh' : 'stale');

        $this->line(
            'db='.($r['database'] ? 'ok' : 'fail')
            .' queue='.$r['queue']
            .' cache='.$r['cache']
            .' failed_jobs='.($r['failed_jobs'] ?? 'unknown')
            .' ndi_media_pending='.($r['ndi_media_pending'] ?? 'unknown')
            .' ndi_media_failed='.($r['ndi_media_failed'] ?? 'unknown')
            .' ndi_stock='.$ndiStock
            .' ndi_age_minutes='.($r['ndi_age_minutes'] ?? 'none')
            .' inbox_configured='.($r['ndi_inbox_configured'] ? 'yes' : 'no')
            .' inbox_directory='.($r['ndi_inbox_directory'] ? 'yes' : 'no')
            .' inbox_readable='.($r['ndi_inbox_readable'] ? 'yes' : 'no')
            .' inbox_failed='.($r['ndi_inbox_failed'] ?? 'unknown')
            .' inbox_retryable='.($r['ndi_inbox_retryable'] ?? 'unknown')
            .' inbox_latest='.($r['ndi_inbox_latest_status'] ?? 'none')
            .' inbox_age_minutes='.($r['ndi_inbox_latest_age_minutes'] ?? 'none')
            .' scheduler='.($r['scheduler_healthy'] ? 'ok' : 'stale')
            .' worker='.($r['queue_healthy'] ? 'ok' : 'stale')
            .' disk='.($r['disk_ok'] ? 'ok' : 'warning')
        );

        return $health->healthy() ? self::SUCCESS : self::FAILURE;
    }
}
