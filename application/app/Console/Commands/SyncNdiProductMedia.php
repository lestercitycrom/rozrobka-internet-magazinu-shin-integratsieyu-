<?php

namespace App\Console\Commands;

use App\Modules\NdiImport\NdiProductMediaSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncNdiProductMedia extends Command
{
    protected $signature = 'ndi:media:sync
        {--limit=25 : Maximum distinct NDI image URLs to download}
        {--force : Retry failed image URLs immediately, ignoring backoff}';

    protected $description = 'Download validated NDI product images into local media storage.';

    public function handle(NdiProductMediaSynchronizer $synchronizer): int
    {
        $lock = Cache::lock('ndi-media-sync', max(600, (int) config('ndi.media_lock_seconds', 900)));

        if (! $lock->get()) {
            $this->components->warn('Another NDI media sync is already running.');

            return self::SUCCESS;
        }

        try {
            $summary = $synchronizer->sync((int) $this->option('limit'), (bool) $this->option('force'));

            if (($summary['disabled'] ?? false) === true) {
                $this->components->info('NDI product media synchronization is disabled.');

                return self::SUCCESS;
            }

            $this->table(['Downloaded', 'Failed', 'Assigned'], [[$summary['downloaded'], $summary['failed'], $summary['assigned']]]);

            return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
