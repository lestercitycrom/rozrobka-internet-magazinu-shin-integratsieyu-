<?php

namespace App\Console\Commands;

use App\Modules\NdiImport\Exceptions\NdiImportAlreadyRunning;
use App\Modules\NdiImport\Exceptions\NdiPublishFailed;
use App\Modules\NdiImport\NdiBundleImporter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ImportNdiBundle extends Command
{
    protected $signature = 'ndi:import
        {files* : Complete XLSX bundle containing tires, wheels, and stock}
        {--trigger=manual : Audit trigger name}
        {--partial : Accept any validated subset of tires, wheels, and stock}
        {--allow-stock-drop : Explicitly allow an exceptional stock snapshot below the automatic drop guard}
        {--redacted : Mark artifacts as synthetic or redacted test data}';

    protected $description = 'Validate and transactionally publish a complete NDI XLSX bundle.';

    public function handle(NdiBundleImporter $importer): int
    {
        try {
            $files = array_values($this->argument('files'));
            $observedAt = $this->oldestFileModification($files);
            $outcome = $this->option('partial')
                ? $importer->importAvailable(
                    $files,
                    (string) $this->option('trigger'),
                    (bool) $this->option('redacted'),
                    (bool) $this->option('allow-stock-drop'),
                    observedAt: $observedAt,
                )
                : $importer->import(
                    $files,
                    (string) $this->option('trigger'),
                    (bool) $this->option('redacted'),
                    (bool) $this->option('allow-stock-drop'),
                    observedAt: $observedAt,
                );
        } catch (NdiImportAlreadyRunning $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (NdiPublishFailed $exception) {
            report($exception);
            $this->components->error("Run {$exception->runId} failed during transactional publication.");

            return self::FAILURE;
        } catch (\Throwable $exception) {
            report($exception);
            $this->components->error('Import failed unexpectedly. Check the application logs.');

            return self::FAILURE;
        }

        $this->components->info("Run {$outcome->runId}: {$outcome->status}.");
        $this->table(
            ['Created', 'Updated', 'Skipped', 'Rejected'],
            [[
                $outcome->createdCount,
                $outcome->updatedCount,
                $outcome->skippedCount,
                $outcome->rejectedCount,
            ]],
        );

        return in_array($outcome->status, ['succeeded', 'duplicate'], true)
            ? self::SUCCESS
            : self::FAILURE;
    }

    /** @param list<string> $files */
    private function oldestFileModification(array $files): ?CarbonImmutable
    {
        $oldest = null;
        foreach ($files as $file) {
            clearstatcache(true, $file);
            if (! is_string($file) || ! is_file($file) || ! is_readable($file)) {
                continue;
            }
            $modifiedAt = filemtime($file);
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
