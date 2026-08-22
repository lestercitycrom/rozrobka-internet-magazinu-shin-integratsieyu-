<?php

namespace App\Modules\Operations;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class SystemHealth
{
    /** @return array<string, mixed> */
    public function report(): array
    {
        $last = null;
        $failed = null;
        $outboxPending = null;
        $outboxFailed = null;
        $ndiMediaPending = null;
        $ndiMediaFailed = null;
        try {
            DB::select('select 1');
            $database = true;
            // A duplicate re-submission does not make the supplier data newer.
            $last = DB::table('import_runs')->whereIn('status', ['succeeded', 'completed'])->whereNotNull('finished_at')->orderByDesc('finished_at')->first(['status', 'finished_at']);
            $failed = DB::table(config('queue.failed.table', 'failed_jobs'))->count();
            $outboxPending = DB::table('outbox_messages')->where('status', 'pending')->count();
            $outboxFailed = DB::table('outbox_messages')->where('status', 'failed')->count();
            $ndiMediaPending = DB::table('product_media_sources')->where('status', 'pending')->count();
            $ndiMediaFailed = DB::table('product_media_sources')->where('status', 'failed')->count();
        } catch (\Throwable) {
            $database = false;
        }
        $age = $last ? max(0, (int) Carbon::parse($last->finished_at)->diffInMinutes(now())) : null;
        $free = @disk_free_space(base_path());
        $schedulerAge = $this->heartbeatAge('easytire.health.scheduler_heartbeat_at');
        $queueAge = $this->heartbeatAge('easytire.health.queue_heartbeat_at');
        $heartbeatLimit = config('health.heartbeat_max_age_minutes');
        $paymentEnabled = (bool) config('payments.enabled');
        $paymentProvider = trim((string) config('payments.provider'));
        $paymentEnvironment = strtolower(trim((string) config('payments.dintero.environment', 'test')));
        $accountKey = $paymentEnvironment === 'production' ? 'production_account_id' : 'test_account_id';
        $accountId = trim((string) config("payments.dintero.{$accountKey}"));
        $expectedPrefix = $paymentEnvironment === 'production' ? 'P' : 'T';
        $paymentConfigured = $paymentProvider === 'dintero'
            && preg_match('/^'.$expectedPrefix.'\d{8}$/', $accountId) === 1
            && trim((string) config('payments.dintero.client_id')) !== ''
            && trim((string) config('payments.dintero.client_secret')) !== '';
        $callbackProtected = (bool) config('payments.dintero.require_callback_signature')
            && trim((string) config('payments.dintero.callback_secret')) !== '';
        $notionEnabled = (bool) config('outbox.notion_enabled');
        $notionConfigured = trim((string) config('outbox.notion.token')) !== ''
            && trim((string) config('outbox.notion.database_id')) !== '';

        return ['database' => $database, 'queue' => config('queue.default'), 'cache' => config('cache.default'), 'failed_jobs' => $failed, 'outbox_pending' => $outboxPending, 'outbox_failed' => $outboxFailed, 'ndi_media_pending' => $ndiMediaPending, 'ndi_media_failed' => $ndiMediaFailed, 'ndi_status' => $last?->status, 'ndi_age_minutes' => $age, 'ndi_fresh' => $age !== null && $age <= config('health.ndi_success_max_age_minutes'), 'scheduler_age_minutes' => $schedulerAge, 'scheduler_healthy' => $schedulerAge !== null && $schedulerAge <= $heartbeatLimit, 'queue_age_minutes' => $queueAge, 'queue_healthy' => $queueAge !== null && $queueAge <= $heartbeatLimit, 'disk_free_bytes' => is_float($free) ? (int) $free : null, 'disk_ok' => $free === false || $free >= config('health.disk_free_warning_bytes'), 'payment_enabled' => $paymentEnabled, 'payment_provider' => $paymentProvider, 'payment_environment' => $paymentEnvironment, 'payment_configured' => $paymentConfigured, 'payment_callback_protected' => $callbackProtected, 'notion_enabled' => $notionEnabled, 'notion_configured' => $notionConfigured];
    }

    public function healthy(): bool
    {
        $report = $this->report();

        $heartbeatsHealthy = ! config('health.require_heartbeats') || ($report['scheduler_healthy'] && $report['queue_healthy']);

        $paymentsHealthy = ! $report['payment_enabled'] || ($report['payment_configured'] && $report['payment_callback_protected']);

        return $report['database'] && $report['failed_jobs'] === 0 && $report['ndi_media_failed'] === 0 && $report['disk_ok'] && ($report['ndi_age_minutes'] === null || $report['ndi_fresh']) && $heartbeatsHealthy && $paymentsHealthy;
    }

    private function heartbeatAge(string $key): ?int
    {
        try {
            $value = Cache::get($key);
        } catch (\Throwable) {
            return null;
        }
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return max(0, (int) Carbon::parse($value)->diffInMinutes(now()));
        } catch (\Throwable) {
            return null;
        }
    }
}
