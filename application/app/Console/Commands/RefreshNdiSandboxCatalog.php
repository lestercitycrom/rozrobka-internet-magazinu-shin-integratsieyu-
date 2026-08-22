<?php

namespace App\Console\Commands;

use App\Modules\NdiApi\NdiSandboxCatalogManager;
use Illuminate\Console\Command;
use Throwable;

final class RefreshNdiSandboxCatalog extends Command
{
    protected $signature = 'ndi:sandbox-catalog {--disable : Hide the fixture without deleting its audit data}';

    protected $description = 'Install or refresh the isolated NDI sandbox catalog product on non-production environments';

    public function handle(NdiSandboxCatalogManager $manager): int
    {
        try {
            if ($this->option('disable')) {
                $manager->disable();
                $this->components->info('NDI Sandbox catalog fixture disabled.');

                return self::SUCCESS;
            }

            $sku = $manager->refresh();
            $this->components->info("NDI Sandbox catalog fixture ready: {$sku->external_sku}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
