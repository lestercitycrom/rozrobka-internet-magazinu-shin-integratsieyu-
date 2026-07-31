<?php

namespace App\Console\Commands;

use App\Modules\NdiImport\Exceptions\NdiImportAlreadyRunning;
use App\Modules\NdiImport\Exceptions\NdiPublishFailed;
use App\Modules\NdiImport\NdiBundleImporter;
use Illuminate\Console\Command;

class ImportNdiBundle extends Command
{
    protected $signature = 'ndi:import
        {files* : Complete XLSX bundle containing tires, wheels, and stock}
        {--trigger=manual : Audit trigger name}
        {--redacted : Mark artifacts as synthetic or redacted test data}';

    protected $description = 'Validate and transactionally publish a complete NDI XLSX bundle.';

    public function handle(NdiBundleImporter $importer): int
    {
        try {
            $outcome = $importer->import(
                array_values($this->argument('files')),
                (string) $this->option('trigger'),
                (bool) $this->option('redacted'),
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
}
