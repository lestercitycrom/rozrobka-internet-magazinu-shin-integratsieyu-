<?php

namespace App\Modules\Operations;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class SystemHealth
{
    /** @return array<string, mixed> */
    public function report(): array
    {
        try {
            DB::select('select 1');
            $database = true;
        } catch (\Throwable) {
            $database = false;
        }
        $last = DB::table('import_runs')->whereIn('status', ['succeeded', 'duplicate', 'completed'])->whereNotNull('finished_at')->orderByDesc('finished_at')->first(['status', 'finished_at']);
        $age = $last ? max(0, (int) Carbon::parse($last->finished_at)->diffInMinutes(now())) : null;
        $free = @disk_free_space(base_path());
        $failed = DB::table(config('queue.failed.table', 'failed_jobs'))->count();
        $outboxPending = DB::table('outbox_messages')->where('status', 'pending')->count();
        $outboxFailed = DB::table('outbox_messages')->where('status', 'failed')->count();

        return ['database' => $database, 'queue' => config('queue.default'), 'cache' => config('cache.default'), 'failed_jobs' => $failed, 'outbox_pending' => $outboxPending, 'outbox_failed' => $outboxFailed, 'ndi_status' => $last?->status, 'ndi_age_minutes' => $age, 'ndi_fresh' => $age !== null && $age <= config('health.ndi_success_max_age_minutes'), 'disk_free_bytes' => is_float($free) ? (int) $free : null, 'disk_ok' => $free === false || $free >= config('health.disk_free_warning_bytes')];
    }

    public function healthy(): bool
    {
        $report = $this->report();

        return $report['database'] && $report['failed_jobs'] === 0 && $report['disk_ok'] && ($report['ndi_age_minutes'] === null || $report['ndi_fresh']);
    }
}
