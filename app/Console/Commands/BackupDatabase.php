<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Nightly database backup into storage/app/backups. Dumps MySQL/MariaDB via
 * mysqldump; copies the file for SQLite. Best-effort; media relies on storage
 * durability.
 */
class BackupDatabase extends Command
{
    protected $signature = 'app:backup-database';

    protected $description = 'Back up the database to storage/app/backups.';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $stamp = now()->format('Y-m-d-His');
        $default = (string) config('database.default');

        if ($default === 'sqlite') {
            $database = (string) config('database.connections.sqlite.database');
            if ($database === ':memory:' || ! is_file($database)) {
                $this->info('In-memory / no SQLite file — nothing to dump.');

                return self::SUCCESS;
            }
            copy($database, $dir.'/db-'.$stamp.'.sqlite');
            $this->info('SQLite backup written.');

            return self::SUCCESS;
        }

        $host = (string) config("database.connections.{$default}.host");
        $port = (string) config("database.connections.{$default}.port");
        $user = (string) config("database.connections.{$default}.username");
        $pass = (string) config("database.connections.{$default}.password");
        $name = (string) config("database.connections.{$default}.database");
        $file = $dir.'/db-'.$stamp.'.sql';

        $command = sprintf(
            'mysqldump --single-transaction --no-tablespaces --result-file=%s --host=%s --port=%s --user=%s %s %s',
            escapeshellarg($file),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            $pass !== '' ? '--password='.escapeshellarg($pass) : '',
            escapeshellarg($name),
        );

        $result = Process::timeout(300)->run($command);
        if ($result->failed()) {
            $this->error('Backup failed: '.$result->errorOutput());

            return self::FAILURE;
        }
        $this->info('Database backup written to '.$file);

        return self::SUCCESS;
    }
}
