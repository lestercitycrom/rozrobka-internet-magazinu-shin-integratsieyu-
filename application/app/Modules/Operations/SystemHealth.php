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
        $ndiSandboxFixtureActive = false;
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
            $ndiSandboxFixtureActive = DB::table('suppliers')
                ->join('supplier_skus', 'supplier_skus.supplier_id', '=', 'suppliers.id')
                ->where('suppliers.code', (string) config('ndi_api.sandbox.supplier_code'))
                ->where('suppliers.is_active', true)
                ->where('supplier_skus.external_sku', (string) config('ndi_api.sandbox.catalog_sku'))
                ->exists();
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
        $ndiSandboxEnabled = (bool) config('ndi_api.sandbox.enabled');
        $ndiSandboxReady = $ndiSandboxEnabled
            && ! app()->environment('production')
            && (bool) config('ndi_api.sandbox.inquiry_enabled')
            && (bool) config('ndi_api.sandbox.order_enabled')
            && (bool) config('ndi_api.c1_test_mode')
            && (string) config('ndi_api.sandbox.party_id') === '1220000'
            && (string) config('ndi_api.sandbox.article_id') === '4765205551649102'
            && $ndiSandboxFixtureActive;

        return ['database' => $database, 'queue' => config('queue.default'), 'cache' => config('cache.default'), 'failed_jobs' => $failed, 'outbox_pending' => $outboxPending, 'outbox_failed' => $outboxFailed, 'ndi_media_pending' => $ndiMediaPending, 'ndi_media_failed' => $ndiMediaFailed, 'ndi_status' => $last?->status, 'ndi_age_minutes' => $age, 'ndi_fresh' => $age !== null && $age <= config('health.ndi_success_max_age_minutes'), 'scheduler_age_minutes' => $schedulerAge, 'scheduler_healthy' => $schedulerAge !== null && $schedulerAge <= $heartbeatLimit, 'queue_age_minutes' => $queueAge, 'queue_healthy' => $queueAge !== null && $queueAge <= $heartbeatLimit, 'disk_free_bytes' => is_float($free) ? (int) $free : null, 'disk_ok' => $free === false || $free >= config('health.disk_free_warning_bytes'), 'payment_enabled' => $paymentEnabled, 'payment_provider' => $paymentProvider, 'payment_environment' => $paymentEnvironment, 'payment_configured' => $paymentConfigured, 'payment_callback_protected' => $callbackProtected, 'notion_enabled' => $notionEnabled, 'notion_configured' => $notionConfigured, 'ndi_sandbox_enabled' => $ndiSandboxEnabled, 'ndi_sandbox_fixture_active' => $ndiSandboxFixtureActive, 'ndi_sandbox_ready' => $ndiSandboxReady];
    }

    public function healthy(): bool
    {
        $report = $this->report();

        $heartbeatsHealthy = ! config('health.require_heartbeats') || ($report['scheduler_healthy'] && $report['queue_healthy']);

        $paymentsHealthy = ! $report['payment_enabled'] || ($report['payment_configured'] && $report['payment_callback_protected']);
        $sandboxHealthy = ! $report['ndi_sandbox_enabled'] || $report['ndi_sandbox_ready'];

        return $report['database'] && $report['failed_jobs'] === 0 && $report['ndi_media_failed'] === 0 && $report['disk_ok'] && ($report['ndi_age_minutes'] === null || $report['ndi_fresh']) && $heartbeatsHealthy && $paymentsHealthy && $sandboxHealthy;
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
