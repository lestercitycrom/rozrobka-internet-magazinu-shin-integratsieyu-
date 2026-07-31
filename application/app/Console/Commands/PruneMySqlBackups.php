<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PruneMySqlBackups extends Command
{
    protected $signature = 'easytire:backup:prune {--keep= : Number of completed backups to retain}';

    protected $description = 'Remove completed MySQL backups beyond the configured retention limit';

    public function handle(): int
    {
        $keep = (int) ($this->option('keep') ?? config('database_backup.retention', 14));
        if ($keep < 1) {
            $this->error('Backup retention must be at least one.');

            return self::FAILURE;
        }

        $backups = glob(storage_path('app/backups/mysql-*.sql.gz')) ?: [];
        $backups = array_values(array_filter($backups, static fn (string $path): bool => is_file($path) && preg_match('/\/mysql-\d{8}_\d{6}_\d{6}\.sql\.gz$/', str_replace('\\', '/', $path)) === 1
        ));
        usort($backups, static fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));

        foreach (array_slice($backups, $keep) as $backup) {
            if (! unlink($backup)) {
                $this->error('Unable to prune an old database backup.');

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
