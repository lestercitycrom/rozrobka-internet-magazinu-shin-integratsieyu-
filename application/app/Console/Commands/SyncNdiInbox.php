<?php

namespace App\Console\Commands;

use App\Modules\Catalog\CatalogDataMode;
use App\Modules\NdiApi\NdiIntegrationProfile;
use App\Modules\NdiImport\NdiBundleImporter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SyncNdiInbox extends Command
{
    protected $signature = 'ndi:inbox:sync {--limit=20 : Maximum stable XLSX files to inspect}';

    protected $description = 'Import new stable NDI XLSX files from the configured receiving inbox.';

    public function handle(NdiBundleImporter $importer): int
    {
        if (! CatalogDataMode::isValid()) {
            $this->components->error('Invalid NDI catalogue mode. Snapshot is allowed only outside production.');

            return self::FAILURE;
        }
        if (CatalogDataMode::isSnapshot()) {
            $this->components->info('NDI inbox paused: staging snapshot mode. No supplier files were processed.');

            return self::SUCCESS;
        }

        $path = trim((string) config('ndi.inbox_path'));
        if ($path === '') {
            $this->components->error('NDI inbox is not configured.');

            return self::FAILURE;
        }
        if (! is_dir($path) || ! is_readable($path)) {
            $this->components->error('The configured NDI inbox is unavailable.');

            return self::FAILURE;
        }

        $inboxRoot = realpath($path);
        if ($inboxRoot === false) {
            $this->components->error('The configured NDI inbox cannot be resolved safely.');

            return self::FAILURE;
        }

        $stableBefore = time() - max(30, (int) config('ndi.inbox_stable_seconds', 120));
        $limit = max(1, min(100, (int) $this->option('limit')));
        $files = collect(scandir($path) ?: [])
            ->reject(fn (string $name): bool => in_array($name, ['.', '..'], true) || str_starts_with($name, '.'))
            ->map(fn (string $name): string => rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name)
            ->filter(fn (string $file): bool => ! is_link($file)
                && is_file($file)
                && is_readable($file)
                && $this->isDirectInboxFile($file, $inboxRoot)
                && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'xlsx'
                && filesize($file) > 0
                && filemtime($file) <= $stableBefore)
            ->sortBy(fn (string $file): int => filemtime($file) ?: 0)
            ->values();

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $handled = 0;
        foreach ($files as $file) {
            if ($handled >= $limit) {
                break;
            }

            clearstatcache(true, $file);
            $stat = stat($file);
            $sha256 = hash_file('sha256', $file);
            if (! is_array($stat) || ! is_string($sha256) || $sha256 === '') {
                $failed++;

                continue;
            }
            $byteSize = (int) $stat['size'];
            $modifiedAt = (int) $stat['mtime'];
            $changedAt = (int) $stat['ctime'];
            $inode = (int) $stat['ino'];
            // A writer resuming after the stability window must not race a partially hashed file.
            clearstatcache(true, $file);
            $afterHash = stat($file);
            if (! is_array($afterHash)
                || (int) $afterHash['size'] !== $byteSize
                || (int) $afterHash['mtime'] !== $modifiedAt
                || (int) $afterHash['ctime'] !== $changedAt
                || (int) $afterHash['ino'] !== $inode) {
                continue;
            }

            $fileKeyHash = hash('sha256', basename($file));
            $fingerprint = hash('sha256', implode('|', [$fileKeyHash, $modifiedAt, $changedAt, $inode, $byteSize, $sha256]));
            $receipt = DB::table('ndi_inbox_receipts')->where('fingerprint', $fingerprint)->first();
            if ($receipt !== null && in_array($receipt->status, ['succeeded', 'failed'], true)) {
                $skipped++;

                continue;
            }
            if ($receipt !== null
                && $receipt->status === 'processing'
                && $receipt->updated_at !== null
                && CarbonImmutable::parse($receipt->updated_at)->isAfter(now()->subMinutes(30))) {
                $skipped++;

                continue;
            }

            if ($receipt === null) {
                $inserted = DB::table('ndi_inbox_receipts')->insertOrIgnore([
                    'fingerprint' => $fingerprint,
                    'file_key_hash' => $fileKeyHash,
                    'sha256' => $sha256,
                    'byte_size' => $byteSize,
                    'file_modified_at' => $modifiedAt,
                    'file_changed_at' => $changedAt,
                    'file_inode' => $inode,
                    'status' => 'processing',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($inserted === 0) {
                    $skipped++;

                    continue;
                }
                $receipt = DB::table('ndi_inbox_receipts')->where('fingerprint', $fingerprint)->first();
            } else {
                $retryBefore = now()->subMinutes(max(1, (int) config('ndi.inbox_retry_backoff_minutes', 10)));
                $claimed = DB::table('ndi_inbox_receipts')
                    ->where('id', $receipt->id)
                    ->where(function ($query) use ($retryBefore): void {
                        $query->where(function ($processing): void {
                            $processing->where('status', 'processing')
                                ->where('updated_at', '<=', now()->subMinutes(30));
                        })->orWhere(function ($retryable) use ($retryBefore): void {
                            $retryable->where('status', 'retryable')
                                ->where('updated_at', '<=', $retryBefore);
                        });
                    })
                    ->update(['status' => 'processing', 'failure_code' => null, 'updated_at' => now()]);
                if ($claimed === 0) {
                    $skipped++;

                    continue;
                }
            }
            if ($receipt === null) {
                $failed++;

                continue;
            }
            $handled++;

            $previousRunId = $this->successfulRunIdForHash($sha256);
            if ($previousRunId !== null) {
                // Content identity is stronger than filename metadata. This check is
                // deliberately parser-version agnostic: an old workbook must not
                // become a new delivery after a parser deployment or an mtime touch.
                DB::table('ndi_inbox_receipts')->where('id', $receipt->id)->update([
                    'status' => 'succeeded',
                    'import_run_id' => $previousRunId,
                    'failure_code' => null,
                    'updated_at' => now(),
                ]);
                $skipped++;

                continue;
            }

            try {
                // The file's stable mtime is the only delivery-time signal supplied by
                // this inbox. Never turn an historical or duplicate workbook into a
                // fresh stock observation merely because the watcher saw it today.
                $outcome = $importer->importAvailable(
                    [$file],
                    'scheduled-inbox',
                    refreshDuplicate: false,
                    observedAt: CarbonImmutable::createFromTimestamp($modifiedAt),
                );
                $succeeded = in_array($outcome->status, ['succeeded', 'duplicate'], true);
                DB::table('ndi_inbox_receipts')->where('id', $receipt->id)->update([
                    'status' => $succeeded ? 'succeeded' : 'failed',
                    'import_run_id' => $outcome->runId,
                    'failure_code' => $succeeded ? null : 'validation_failed',
                    'updated_at' => now(),
                ]);
                if ($outcome->status === 'succeeded') {
                    $imported++;
                } elseif ($outcome->status === 'duplicate') {
                    $skipped++;
                } else {
                    $failed++;
                }
            } catch (Throwable $exception) {
                report($exception);
                DB::table('ndi_inbox_receipts')->where('id', $receipt->id)->update([
                    'status' => 'retryable',
                    'failure_code' => 'unexpected_import_failure',
                    'updated_at' => now(),
                ]);
                $failed++;
            }
        }

        $this->components->info("NDI inbox: {$imported} imported, {$skipped} unchanged, {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function isDirectInboxFile(string $file, string $inboxRoot): bool
    {
        $resolved = realpath($file);

        return $resolved !== false
            && hash_equals(rtrim($inboxRoot, DIRECTORY_SEPARATOR), dirname($resolved));
    }

    private function successfulRunIdForHash(string $sha256): ?string
    {
        $runId = DB::table('source_artifacts')
            ->join('suppliers', 'suppliers.id', '=', 'source_artifacts.supplier_id')
            ->join('import_run_artifacts', 'import_run_artifacts.source_artifact_id', '=', 'source_artifacts.id')
            ->join('import_runs', 'import_runs.id', '=', 'import_run_artifacts.import_run_id')
            ->where('suppliers.code', (string) config('ndi.supplier_code'))
            ->where('suppliers.integration_profile', NdiIntegrationProfile::LIVE)
            ->where('suppliers.is_test_fixture', false)
            ->where('source_artifacts.sha256', $sha256)
            ->whereIn('import_run_artifacts.dataset_role', ['tires', 'wheels', 'stock'])
            ->whereIn('import_runs.status', ['succeeded', 'completed'])
            ->orderByDesc('import_runs.finished_at')
            ->value('import_runs.id');

        return is_string($runId) && $runId !== '' ? $runId : null;
    }
}
