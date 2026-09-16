<?php

namespace App\Modules\Operations;

use App\Modules\Catalog\CatalogDataMode;
use App\Modules\Catalog\Enums\CatalogState;
use App\Modules\Catalog\Enums\PublicationStatus;
use App\Modules\Catalog\ReadModel\CatalogReadService;
use App\Modules\Catalog\ReadModel\StockFreshnessPolicy;
use App\Modules\NdiApi\NdiIntegrationProfile;
use App\Modules\NdiApi\NdiSandboxContract;
use App\Modules\Pricing\SellingPriceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class SystemHealth
{
    public function __construct(
        private readonly CatalogReadService $catalog,
        private readonly SellingPriceService $pricing,
    ) {}

    /** @return array<string, mixed> */
    public function report(): array
    {
        $lastLiveStockImport = null;
        $oldestLiveStockObservation = null;
        $latestInboxReceipt = null;
        $failed = null;
        $outboxPending = null;
        $outboxFailed = null;
        $ndiMediaPending = null;
        $ndiMediaFailed = null;
        $ndiInboxFailed = null;
        $ndiInboxRetryable = null;
        $ndiLiveCatalogPublished = false;
        $ndiSandboxFixtureActive = false;
        $ndiSandboxFixtureStockReady = false;
        $ndiSandboxFixturePriceReady = false;

        try {
            DB::select('select 1');
            $database = true;

            $lastLiveStockImport = DB::table('import_runs')
                ->join('suppliers', 'suppliers.id', '=', 'import_runs.supplier_id')
                ->join('import_run_artifacts', 'import_run_artifacts.import_run_id', '=', 'import_runs.id')
                ->where('suppliers.code', (string) config('ndi.supplier_code', 'ndi'))
                ->where('suppliers.integration_profile', NdiIntegrationProfile::LIVE)
                ->where('suppliers.is_test_fixture', false)
                ->where('suppliers.is_active', true)
                ->where('import_run_artifacts.dataset_role', 'stock')
                // A duplicate re-submission does not make the supplier data newer.
                ->whereIn('import_runs.status', ['succeeded', 'completed'])
                ->whereNotNull('import_runs.finished_at')
                ->orderByDesc('import_runs.finished_at')
                ->orderByDesc('import_runs.id')
                ->first(['import_runs.status', 'import_runs.finished_at']);

            $ndiLiveCatalogPublished = DB::table('supplier_skus')
                ->join('suppliers', 'suppliers.id', '=', 'supplier_skus.supplier_id')
                ->join('products', 'products.id', '=', 'supplier_skus.product_id')
                ->where('suppliers.code', (string) config('ndi.supplier_code', 'ndi'))
                ->where('suppliers.integration_profile', NdiIntegrationProfile::LIVE)
                ->where('suppliers.is_test_fixture', false)
                ->where('suppliers.is_active', true)
                ->where('supplier_skus.catalog_state', CatalogState::Catalog->value)
                ->where('products.publication_status', PublicationStatus::Published->value)
                ->exists();

            // A newly completed import cannot make an old delivery fresh. The
            // publisher records the authoritative delivery time on every current
            // stock row; the oldest row is used so one partial/future observation
            // cannot mask stale supplier state.
            $oldestLiveStockObservation = DB::table('current_stock')
                ->join('supplier_skus', 'supplier_skus.id', '=', 'current_stock.supplier_sku_id')
                ->join('suppliers', 'suppliers.id', '=', 'supplier_skus.supplier_id')
                ->where('suppliers.code', (string) config('ndi.supplier_code', 'ndi'))
                ->where('suppliers.integration_profile', NdiIntegrationProfile::LIVE)
                ->where('suppliers.is_test_fixture', false)
                ->where('suppliers.is_active', true)
                ->min('current_stock.observed_at');

            $failed = DB::table(config('queue.failed.table', 'failed_jobs'))->count();
            $outboxPending = DB::table('outbox_messages')->where('status', 'pending')->count();
            $outboxFailed = DB::table('outbox_messages')->where('status', 'failed')->count();
            $ndiMediaPending = DB::table('product_media_sources')->where('status', 'pending')->count();
            $ndiMediaFailed = DB::table('product_media_sources')->where('status', 'failed')->count();
            $ndiInboxFailed = DB::table('ndi_inbox_receipts')->where('status', 'failed')->count();
            $ndiInboxRetryable = DB::table('ndi_inbox_receipts')->where('status', 'retryable')->count();
            $latestInboxReceipt = DB::table('ndi_inbox_receipts')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first(['status', 'updated_at']);

            $sandboxFixtureSkuId = DB::table('suppliers')
                ->join('supplier_skus', 'supplier_skus.supplier_id', '=', 'suppliers.id')
                ->join('products', 'products.id', '=', 'supplier_skus.product_id')
                ->where('suppliers.code', (string) config('ndi_api.sandbox.supplier_code'))
                ->where('suppliers.integration_profile', NdiIntegrationProfile::SANDBOX)
                ->where('suppliers.is_test_fixture', true)
                ->where('suppliers.is_active', true)
                ->where('supplier_skus.external_sku', (string) config('ndi_api.sandbox.catalog_sku'))
                ->where('supplier_skus.catalog_state', CatalogState::Catalog->value)
                ->where('products.publication_status', PublicationStatus::Published->value)
                ->value('supplier_skus.id');
            $ndiSandboxFixtureActive = $sandboxFixtureSkuId !== null;

            if ($ndiSandboxFixtureActive) {
                $sandboxItem = $this->catalog->findPublicSku(NdiSandboxContract::CATALOG_SKU);
                $sandboxPrice = $sandboxItem === null ? null : $this->pricing->forItem($sandboxItem);
                $fixtureIdentityReady = $sandboxItem?->isIntegrationTest === true
                    && $sandboxItem->supplierCode === (string) config('ndi_api.sandbox.supplier_code')
                    && $sandboxItem->integrationProfile === NdiIntegrationProfile::SANDBOX
                    && $sandboxItem->supplierArticleId === NdiSandboxContract::ARTICLE_ID;
                $ndiSandboxFixtureStockReady = $fixtureIdentityReady
                    && $sandboxItem->stock->canPurchase(NdiSandboxContract::QUANTITY);
                $ndiSandboxFixturePriceReady = $fixtureIdentityReady
                    && $sandboxPrice?->money->minor === NdiSandboxContract::UNIT_PRICE_MINOR
                    && $sandboxPrice?->money->currency === NdiSandboxContract::CURRENCY;
            }
        } catch (\Throwable) {
            $database = false;
        }

        $ndiImportAge = $this->age($lastLiveStockImport?->finished_at);
        $ndiStockAge = $this->age($oldestLiveStockObservation);
        $ndiAge = $ndiImportAge === null || $ndiStockAge === null
            ? null
            : max($ndiImportAge, $ndiStockAge);
        $ndiFresh = $ndiAge !== null
            && StockFreshnessPolicy::isFresh($lastLiveStockImport?->finished_at)
            && StockFreshnessPolicy::isFresh($oldestLiveStockObservation)
            && $ndiAge <= max(0, (int) config('health.ndi_success_max_age_minutes'));
        $ndiHealthy = $ndiAge === null ? ! $ndiLiveCatalogPublished : $ndiFresh;

        $latestInboxStatus = $this->receiptStatus($latestInboxReceipt?->status);
        $latestInboxAge = $this->age($latestInboxReceipt?->updated_at);
        $inboxPath = trim((string) config('ndi.inbox_path'));
        $inboxConfigured = $inboxPath !== '';
        $inboxDirectory = $inboxConfigured && @is_dir($inboxPath);
        $inboxReadable = $inboxConfigured && @is_readable($inboxPath);
        $inboxReady = $inboxConfigured && $inboxDirectory && $inboxReadable;
        // Failed/retryable history remains visible in the counters. Without an explicit
        // acknowledgement model it cannot be a permanent global gate: a corrected file
        // would otherwise never recover health. Gate on the latest receipt lifecycle.
        $inboxReceiptsHealthy = $latestInboxStatus === null
            || $latestInboxStatus === 'succeeded'
            || ($latestInboxStatus === 'processing' && $latestInboxAge !== null && $latestInboxAge <= 30);

        $free = @disk_free_space(base_path());
        $schedulerAge = $this->heartbeatAge('easytire.health.scheduler_heartbeat_at');
        $queueAge = $this->heartbeatAge('easytire.health.queue_heartbeat_at');
        $heartbeatLimit = config('health.heartbeat_max_age_minutes');
        $paymentEnabled = (bool) config('payments.enabled');
        $rawPaymentProvider = (string) config('payments.provider');
        $paymentProvider = trim($rawPaymentProvider);
        $paymentEnvironment = strtolower(trim((string) config('payments.dintero.environment', 'test')));
        $paymentEnvironmentValid = in_array($paymentEnvironment, ['test', 'production'], true);
        $accountKey = $paymentEnvironment === 'production' ? 'production_account_id' : 'test_account_id';
        $accountId = trim((string) config("payments.dintero.{$accountKey}"));
        $expectedPrefix = $paymentEnvironment === 'production' ? 'P' : 'T';
        $paymentConfigured = $rawPaymentProvider === 'dintero'
            && $paymentEnvironmentValid
            && (! app()->environment('production') || $paymentEnvironment === 'production')
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
            && (string) config('ndi_api.sandbox.party_id') === NdiSandboxContract::PARTY_ID
            && (string) config('ndi_api.sandbox.article_id') === NdiSandboxContract::ARTICLE_ID
            && (string) config('ndi_api.sandbox.catalog_sku') === NdiSandboxContract::CATALOG_SKU
            && (int) config('ndi_api.sandbox.quantity', NdiSandboxContract::QUANTITY) === NdiSandboxContract::QUANTITY
            && (int) config('ndi_api.sandbox.price_minor', NdiSandboxContract::UNIT_PRICE_MINOR) === NdiSandboxContract::UNIT_PRICE_MINOR
            && $ndiSandboxFixtureActive
            && $ndiSandboxFixtureStockReady
            && $ndiSandboxFixturePriceReady;

        return [
            'database' => $database,
            'ndi_catalog_mode' => CatalogDataMode::mode(),
            'ndi_catalog_mode_valid' => CatalogDataMode::isValid(),
            'ndi_import_paused' => CatalogDataMode::isSnapshot(),
            'queue' => config('queue.default'),
            'cache' => config('cache.default'),
            'failed_jobs' => $failed,
            'outbox_pending' => $outboxPending,
            'outbox_failed' => $outboxFailed,
            'ndi_media_pending' => $ndiMediaPending,
            'ndi_media_failed' => $ndiMediaFailed,
            'ndi_status' => $lastLiveStockImport?->status,
            'ndi_age_minutes' => $ndiAge,
            'ndi_fresh' => $ndiFresh,
            'ndi_live_catalog_published' => $ndiLiveCatalogPublished,
            'ndi_healthy' => $ndiHealthy,
            'ndi_inbox_configured' => $inboxConfigured,
            'ndi_inbox_directory' => $inboxDirectory,
            'ndi_inbox_readable' => $inboxReadable,
            'ndi_inbox_ready' => $inboxReady,
            'ndi_inbox_failed' => $ndiInboxFailed,
            'ndi_inbox_retryable' => $ndiInboxRetryable,
            'ndi_inbox_latest_status' => $latestInboxStatus,
            'ndi_inbox_latest_age_minutes' => $latestInboxAge,
            'ndi_inbox_receipts_healthy' => $inboxReceiptsHealthy,
            'scheduler_age_minutes' => $schedulerAge,
            'scheduler_healthy' => $schedulerAge !== null && $schedulerAge <= $heartbeatLimit,
            'queue_age_minutes' => $queueAge,
            'queue_healthy' => $queueAge !== null && $queueAge <= $heartbeatLimit,
            'disk_free_bytes' => is_float($free) ? (int) $free : null,
            'disk_ok' => $free === false || $free >= config('health.disk_free_warning_bytes'),
            'payment_enabled' => $paymentEnabled,
            'payment_provider' => $paymentProvider,
            'payment_environment' => $paymentEnvironmentValid ? $paymentEnvironment : 'invalid',
            'payment_configured' => $paymentConfigured,
            'payment_callback_protected' => $callbackProtected,
            'notion_enabled' => $notionEnabled,
            'notion_configured' => $notionConfigured,
            'ndi_sandbox_enabled' => $ndiSandboxEnabled,
            'ndi_sandbox_fixture_active' => $ndiSandboxFixtureActive,
            'ndi_sandbox_fixture_stock_ready' => $ndiSandboxFixtureStockReady,
            'ndi_sandbox_fixture_price_ready' => $ndiSandboxFixturePriceReady,
            'ndi_sandbox_ready' => $ndiSandboxReady,
        ];
    }

    public function healthy(): bool
    {
        $report = $this->report();

        $heartbeatsHealthy = ! config('health.require_heartbeats') || ($report['scheduler_healthy'] && $report['queue_healthy']);
        $paymentsHealthy = ! $report['payment_enabled'] || ($report['payment_configured'] && $report['payment_callback_protected']);
        $sandboxHealthy = ! $report['ndi_sandbox_enabled'] || $report['ndi_sandbox_ready'];
        $inboxHealthy = $report['ndi_inbox_ready'] && $report['ndi_inbox_receipts_healthy'];

        return $report['database']
            && $report['ndi_catalog_mode_valid']
            && $report['failed_jobs'] === 0
            && $report['ndi_media_failed'] === 0
            && $report['disk_ok']
            && $report['ndi_healthy']
            && $inboxHealthy
            && $heartbeatsHealthy
            && $paymentsHealthy
            && $sandboxHealthy;
    }

    private function age(mixed $value): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return max(0, (int) Carbon::parse($value)->diffInMinutes(now()));
        } catch (\Throwable) {
            return null;
        }
    }

    private function heartbeatAge(string $key): ?int
    {
        try {
            return $this->age(Cache::get($key));
        } catch (\Throwable) {
            return null;
        }
    }

    private function receiptStatus(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $status = strtolower(trim($value));

        return in_array($status, ['processing', 'succeeded', 'failed', 'retryable'], true)
            ? $status
            : 'unknown';
    }
}
