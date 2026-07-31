<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class BackupMySqlDatabase extends Command
{
    protected $signature = 'easytire:backup:mysql';

    protected $description = 'Create an atomic compressed MySQL backup before a deployment migration';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->error('MySQL backup is required before migrations, but the active connection is not MySQL.');

            return self::FAILURE;
        }

        /** @var array<string, mixed> $connection */
        $connection = config('database.connections.mysql', []);
        if (! $this->hasRequiredConnectionValues($connection)) {
            $this->error('MySQL backup is required before migrations, but its connection configuration is incomplete.');

            return self::FAILURE;
        }

        $directory = storage_path('app/backups');
        $timestamp = now()->utc()->format('Ymd_His_u');
        $finalPath = $directory."/mysql-{$timestamp}.sql.gz";
        $gzipPartialPath = "{$finalPath}.partial";
        $sqlPartialPath = "{$gzipPartialPath}.sql";

        try {
            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new RuntimeException('Unable to create backup directory.');
            }

            @chmod($directory, 0700);
            $this->writeDump($connection, $sqlPartialPath);

            if (! is_file($sqlPartialPath) || filesize($sqlPartialPath) === 0) {
                throw new RuntimeException('mysqldump did not create a usable backup.');
            }

            $this->gzip($sqlPartialPath, $gzipPartialPath);
            clearstatcache(true, $gzipPartialPath);
            if (! is_file($gzipPartialPath) || filesize($gzipPartialPath) === 0) {
                throw new RuntimeException('Compressed backup is empty.');
            }

            @chmod($gzipPartialPath, 0600);
            if (! rename($gzipPartialPath, $finalPath)) {
                throw new RuntimeException('Unable to activate backup.');
            }
            @chmod($finalPath, 0600);
        } catch (Throwable) {
            @unlink($gzipPartialPath);
            $this->error('Database backup failed; migration has been aborted.');

            return self::FAILURE;
        } finally {
            @unlink($sqlPartialPath);
        }

        $this->info('MySQL backup completed.');

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $connection */
    private function hasRequiredConnectionValues(array $connection): bool
    {
        return filled($connection['host'] ?? null)
            && filled($connection['port'] ?? null)
            && filled($connection['database'] ?? null)
            && filled($connection['username'] ?? null);
    }

    /** @param array<string, mixed> $connection */
    private function writeDump(array $connection, string $destination): void
    {
        $password = (string) ($connection['password'] ?? '');
        $environment = getenv();
        $environment = is_array($environment) ? $environment : [];
        unset($environment['MYSQL_PWD']);
        if ($password !== '') {
            $environment['MYSQL_PWD'] = $password;
        }

        if (file_put_contents($destination, '') === false || ! chmod($destination, 0600)) {
            throw new RuntimeException('Unable to prepare backup destination.');
        }

        $command = [
            (string) config('database_backup.mysqldump_binary', 'mysqldump'),
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--no-tablespaces',
            '--set-gtid-purged=OFF',
            '--default-character-set=utf8mb4',
            '--routines',
            '--triggers',
            '--events',
            '--host='.(string) $connection['host'],
            '--port='.(string) $connection['port'],
            '--user='.(string) $connection['username'],
            (string) $connection['database'],
        ];

        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['file', $destination, 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, null, $environment, ['bypass_shell' => true]);

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start mysqldump.');
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        if (proc_close($process) !== 0) {
            throw new RuntimeException('mysqldump failed.');
        }
    }

    private function gzip(string $source, string $destination): void
    {
        if (file_put_contents($destination, '') === false || ! chmod($destination, 0600)) {
            throw new RuntimeException('Unable to prepare compressed backup destination.');
        }

        $input = fopen($source, 'rb');
        $output = gzopen($destination, 'wb9');

        if ($input === false || $output === false) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                gzclose($output);
            }
            throw new RuntimeException('Unable to compress database backup.');
        }

        try {
            while (! feof($input)) {
                $chunk = fread($input, 8192);
                if ($chunk === false || ($chunk !== '' && gzwrite($output, $chunk) !== strlen($chunk))) {
                    throw new RuntimeException('Unable to compress database backup.');
                }
            }
        } finally {
            fclose($input);
            gzclose($output);
        }
    }
}
