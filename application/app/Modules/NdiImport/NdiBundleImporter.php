<?php

namespace App\Modules\NdiImport;

use App\Modules\Catalog\Models\Supplier;
use App\Modules\NdiImport\Data\ImportOutcome;
use App\Modules\NdiImport\Data\PreparedDataset;
use App\Modules\NdiImport\Data\StoredArtifact;
use App\Modules\NdiImport\Enums\DatasetRole;
use App\Modules\NdiImport\Exceptions\NdiImportAlreadyRunning;
use App\Modules\NdiImport\Exceptions\NdiPublishFailed;
use App\Modules\NdiImport\Exceptions\UnknownWorkbookSchema;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class NdiBundleImporter
{
    private const MAX_FUTURE_OBSERVATION_MINUTES = 5;

    public function __construct(
        private readonly NdiArtifactStore $artifactStore,
        private readonly NdiWorkbookParser $parser,
        private readonly NdiWorkbookValidator $validator,
        private readonly NdiCatalogPublisher $publisher,
    ) {}

    /**
     * @param  list<string>  $sourcePaths
     */
    public function import(
        array $sourcePaths,
        string $trigger = 'manual',
        bool $isRedacted = false,
        bool $allowStockDrop = false,
        ?CarbonImmutable $observedAt = null,
    ): ImportOutcome {
        return $this->importWithMode($sourcePaths, $trigger, $isRedacted, true, false, $allowStockDrop, $observedAt);
    }

    /** @param list<string> $sourcePaths */
    public function importAvailable(
        array $sourcePaths,
        string $trigger = 'inbox',
        bool $isRedacted = false,
        bool $allowStockDrop = false,
        bool $refreshDuplicate = false,
        ?CarbonImmutable $observedAt = null,
    ): ImportOutcome {
        return $this->importWithMode($sourcePaths, $trigger, $isRedacted, false, $refreshDuplicate, $allowStockDrop, $observedAt);
    }

    /** @param list<string> $sourcePaths */
    private function importWithMode(
        array $sourcePaths,
        string $trigger,
        bool $isRedacted,
        bool $requireComplete,
        bool $refreshDuplicate,
        bool $allowStockDrop,
        ?CarbonImmutable $observedAt,
    ): ImportOutcome {
        if ($sourcePaths === []) {
            throw new InvalidArgumentException('At least one NDI source artifact is required.');
        }
        $observedAt ??= $this->oldestSourceModification($sourcePaths);
        if ($observedAt?->isAfter(CarbonImmutable::now()->addMinutes(self::MAX_FUTURE_OBSERVATION_MINUTES))) {
            throw new InvalidArgumentException('NDI observation time is too far in the future.');
        }

        $supplierCode = (string) config('ndi.supplier_code');
        $lock = Cache::lock(
            "ndi-import:{$supplierCode}",
            max(300, (int) config('ndi.import_lock_seconds', 1800)),
        );

        if (! $lock->get()) {
            throw new NdiImportAlreadyRunning('Another NDI import is already running.');
        }

        try {
            return $this->performImport($sourcePaths, $trigger, $isRedacted, $requireComplete, $refreshDuplicate, $allowStockDrop, $observedAt);
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * @param  list<string>  $sourcePaths
     */
    private function performImport(
        array $sourcePaths,
        string $trigger,
        bool $isRedacted,
        bool $requireComplete,
        bool $refreshDuplicate,
        bool $allowStockDrop,
        ?CarbonImmutable $observedAt,
    ): ImportOutcome {
        $supplierCode = (string) config('ndi.supplier_code');
        $parserVersion = (string) config('ndi.parser_version');
        $supplier = Supplier::query()->firstOrCreate(
            ['code' => $supplierCode],
            ['name' => 'NDI', 'is_active' => true],
        );

        /** @var list<array{artifact: StoredArtifact, path: string}> $stored */
        $stored = [];

        foreach ($sourcePaths as $sourcePath) {
            $artifact = $this->artifactStore->store(
                $supplier->id,
                $supplierCode,
                $sourcePath,
                $isRedacted,
                $observedAt,
            );
            $stored[] = [
                'artifact' => $artifact,
                'path' => Storage::disk((string) config('ndi.raw_disk'))->path($artifact->storageKey),
            ];
        }

        foreach ($stored as $item) {
            if ($observedAt === null || $item['artifact']->receivedAt->isBefore($observedAt)) {
                $observedAt = $item['artifact']->receivedAt;
            }
        }

        $runId = (string) Str::ulid();
        DB::table('import_runs')->insert([
            'id' => $runId,
            'supplier_id' => $supplier->id,
            'run_type' => $requireComplete ? 'catalog_bundle' : 'partial_feed',
            'status' => 'running',
            'trigger' => Str::limit($trigger, 64, ''),
            'parser_version' => $parserVersion,
            'started_at' => now(),
            'finished_at' => null,
            'created_count' => 0,
            'updated_count' => 0,
            'skipped_count' => 0,
            'rejected_count' => 0,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, array{artifact: StoredArtifact, path: string}> $detected */
        $detected = [];
        $bundleErrors = 0;
        $linkedArtifacts = [];

        foreach ($stored as $index => $item) {
            $artifact = $item['artifact'];

            if (isset($linkedArtifacts[$artifact->id])) {
                $this->writeRejection($runId, $artifact->id, null, null, null, 'duplicate_artifact_in_bundle', null);
                $bundleErrors++;

                continue;
            }

            $linkedArtifacts[$artifact->id] = true;

            try {
                $role = $this->parser->inspect($item['path'])->role;
                $datasetRole = $role->value;
            } catch (UnknownWorkbookSchema $exception) {
                $datasetRole = 'unknown-'.($index + 1);
                $this->linkArtifact($runId, $artifact->id, $datasetRole, $parserVersion);
                $this->writeRejection($runId, $artifact->id, null, null, null, $exception->getMessage(), null);
                $bundleErrors++;

                continue;
            }

            $this->linkArtifact($runId, $artifact->id, $datasetRole, $parserVersion);

            if (isset($detected[$datasetRole])) {
                $this->writeRejection($runId, $artifact->id, null, null, null, "duplicate_dataset_role:{$datasetRole}", null);
                $bundleErrors++;

                continue;
            }

            $detected[$datasetRole] = $item;
        }

        $missingRoles = array_values(array_diff(
            array_map(fn (DatasetRole $role): string => $role->value, DatasetRole::cases()),
            array_keys($detected),
        ));

        if ($requireComplete && $missingRoles !== []) {
            $firstArtifact = $stored[0]['artifact'];
            $this->writeRejection(
                $runId,
                $firstArtifact->id,
                null,
                null,
                null,
                'missing_dataset_roles:'.implode(',', $missingRoles),
                null,
            );
            $bundleErrors++;
        }

        $duplicateOf = $bundleErrors === 0 && ! $refreshDuplicate
            ? $this->duplicateRunId($supplier->id, $parserVersion, $detected)
            : null;

        if ($duplicateOf !== null) {
            $this->finishRun($runId, 'duplicate', metadata: ['duplicate_of' => $duplicateOf]);

            return $this->outcome($runId);
        }

        /** @var array<string, PreparedDataset> $prepared */
        $prepared = [];
        $validated = [];
        $rejectedCount = $bundleErrors;

        if ($bundleErrors === 0) {
            foreach ($detected as $roleValue => $item) {
                $workbook = $this->validator->validate($item['path']);
                $validated[] = $workbook;

                foreach ($workbook->rejections as $rejection) {
                    $this->writeRejection(
                        $runId,
                        $item['artifact']->id,
                        $rejection->rowNumber,
                        $rejection->externalSku,
                        $rejection->fieldName,
                        $rejection->reason,
                        $rejection->rawRow,
                    );
                    $rejectedCount++;
                }

                if ($workbook->isValid()) {
                    $prepared[$roleValue] = new PreparedDataset(
                        DatasetRole::from($roleValue),
                        $item['artifact'],
                        $workbook,
                    );
                }
            }
        }

        foreach ($prepared as $dataset) {
            if ($dataset->workbook->acceptedCount === 0) {
                $this->writeRejection($runId, $dataset->artifact->id, null, null, null, 'dataset_contains_no_rows', null);
                $rejectedCount++;
            }
        }

        $stock = $prepared[DatasetRole::Stock->value] ?? null;
        if ($stock !== null
            && ! $allowStockDrop
            && $this->isUnsafeStockDrop($supplier->id, $stock->workbook->acceptedCount)) {
            $this->writeRejection($runId, $stock->artifact->id, null, null, null, 'stock_row_count_drop_requires_manual_override', null);
            $rejectedCount++;
        }

        try {
            if ($rejectedCount > 0 || count($prepared) !== count($detected) || $prepared === []) {
                $this->finishRun(
                    $runId,
                    'failed',
                    rejectedCount: $rejectedCount,
                    metadata: ['reason' => 'bundle_validation_failed'],
                );

                return $this->outcome($runId);
            }

            $hasCatalog = isset($prepared[DatasetRole::Tires->value]) || isset($prepared[DatasetRole::Wheels->value]);
            $currency = mb_strtoupper(trim((string) config('ndi.catalog_currency')));

            if ($hasCatalog && preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
                $firstArtifact = $stored[0]['artifact'];
                $this->writeRejection($runId, $firstArtifact->id, null, null, null, 'catalog_currency_not_configured', null);
                $this->finishRun(
                    $runId,
                    'failed',
                    rejectedCount: 1,
                    metadata: ['reason' => 'catalog_currency_not_configured'],
                );

                return $this->outcome($runId);
            }
            $currency = preg_match('/^[A-Z]{3}$/D', $currency) === 1 ? $currency : 'NOK';

            try {
                DB::transaction(function () use ($supplier, $runId, $prepared, $currency, $observedAt): void {
                    $summary = $this->publisher->publish($supplier, $runId, $prepared, $currency, $observedAt);
                    $this->finishRun(
                        $runId,
                        'succeeded',
                        $summary->created,
                        $summary->updated,
                        $summary->skipped,
                    );
                }, attempts: 1);
            } catch (Throwable $exception) {
                $this->finishRun(
                    $runId,
                    'failed',
                    rejectedCount: 0,
                    metadata: ['reason' => 'transactional_publish_failed'],
                );

                throw new NdiPublishFailed($runId, $exception);
            }

            return $this->outcome($runId);
        } finally {
            foreach ($validated as $workbook) {
                $workbook->cleanup();
            }
        }
    }

    /**
     * @param  array<string, array{artifact: StoredArtifact, path: string}>  $detected
     */
    private function duplicateRunId(int $supplierId, string $parserVersion, array $detected): ?string
    {
        $expectedPairs = collect($detected)
            ->map(fn (array $item, string $role): string => $role.':'.$item['artifact']->id)
            ->sort()
            ->values()
            ->all();

        $candidateIds = DB::table('import_runs')
            ->where('supplier_id', $supplierId)
            ->where('parser_version', $parserVersion)
            ->where('status', 'succeeded')
            ->pluck('id');

        foreach ($candidateIds as $candidateId) {
            $candidatePairs = DB::table('import_run_artifacts')
                ->where('import_run_id', $candidateId)
                ->get(['dataset_role', 'source_artifact_id'])
                ->map(fn (object $link): string => $link->dataset_role.':'.$link->source_artifact_id)
                ->sort()
                ->values()
                ->all();

            if ($candidatePairs === $expectedPairs) {
                return (string) $candidateId;
            }
        }

        return null;
    }

    private function isUnsafeStockDrop(int $supplierId, int $incomingCount): bool
    {
        $baseline = DB::table('current_stock')
            ->join('supplier_skus', 'supplier_skus.id', '=', 'current_stock.supplier_sku_id')
            ->where('supplier_skus.supplier_id', $supplierId)
            ->distinct()
            ->count('supplier_skus.id');
        $minimumBaseline = max(1, (int) config('ndi.stock_drop_guard_min_baseline', 50));
        $ratio = max(0.1, min(1.0, (float) config('ndi.stock_minimum_previous_ratio', 0.9)));

        return $baseline >= $minimumBaseline
            && $incomingCount < (int) ceil($baseline * $ratio);
    }

    private function linkArtifact(string $runId, string $artifactId, string $datasetRole, string $parserVersion): void
    {
        DB::table('import_run_artifacts')->insert([
            'import_run_id' => $runId,
            'source_artifact_id' => $artifactId,
            'dataset_role' => $datasetRole,
            'parser_version' => $parserVersion,
        ]);
    }

    /** @param array<string, mixed>|null $rawRow */
    private function writeRejection(
        string $runId,
        string $artifactId,
        ?int $rowNumber,
        ?string $externalSku,
        ?string $fieldName,
        string $reason,
        ?array $rawRow,
    ): void {
        DB::table('import_rejections')->insert([
            'import_run_id' => $runId,
            'source_artifact_id' => $artifactId,
            'sheet_name' => $rowNumber === null ? null : 'Article',
            'row_number' => $rowNumber,
            'external_sku' => $externalSku,
            'field_name' => $fieldName,
            'reason' => Str::limit($reason, 255, ''),
            'raw_row_json' => $rawRow === null ? null : json_encode($rawRow, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed>|null $metadata */
    private function finishRun(
        string $runId,
        string $status,
        int $createdCount = 0,
        int $updatedCount = 0,
        int $skippedCount = 0,
        int $rejectedCount = 0,
        ?array $metadata = null,
    ): void {
        DB::table('import_runs')->where('id', $runId)->update([
            'status' => $status,
            'finished_at' => now(),
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
            'skipped_count' => $skippedCount,
            'rejected_count' => $rejectedCount,
            'metadata' => $metadata === null ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    private function outcome(string $runId): ImportOutcome
    {
        $run = DB::table('import_runs')->where('id', $runId)->first();

        return new ImportOutcome(
            $run->id,
            $run->status,
            (int) $run->created_count,
            (int) $run->updated_count,
            (int) $run->skipped_count,
            (int) $run->rejected_count,
        );
    }

    private function releaseLock(Lock $lock): void
    {
        try {
            $lock->release();
        } catch (Throwable) {
            // The short-lived process lock expires automatically if its backing store disappears.
        }
    }

    /** @param list<string> $sourcePaths */
    private function oldestSourceModification(array $sourcePaths): ?CarbonImmutable
    {
        $oldest = null;
        foreach ($sourcePaths as $sourcePath) {
            clearstatcache(true, $sourcePath);
            $modifiedAt = is_file($sourcePath) && is_readable($sourcePath)
                ? filemtime($sourcePath)
                : false;
            if ($modifiedAt === false) {
                continue;
            }
            $candidate = CarbonImmutable::createFromTimestamp($modifiedAt);
            if ($oldest === null || $candidate->isBefore($oldest)) {
                $oldest = $candidate;
            }
        }

        return $oldest;
    }
}
