<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Dump MySQL → S3 y purga backups viejos (patrón comfy_back_v2, adaptado).
 */
class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup
                            {--keep-days=10 : Delete S3 backups older than this many days after a successful upload}';

    protected $description = 'Dump the MySQL database, upload to S3, and prune backups older than keep-days.';

    public function handle(): int
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "db_backup_{$timestamp}.sql.gz";
        $tempPath = sys_get_temp_dir().'/'.$filename;
        $s3Key = 'backups/'.now()->format('Y/m').'/'.$filename;

        $connection = config('database.connections.'.config('database.default'));

        if (($connection['driver'] ?? '') !== 'mysql') {
            $this->error('db:backup solo soporta MySQL (driver actual: '.($connection['driver'] ?? 'none').').');

            return self::FAILURE;
        }

        $dump = new Process([
            'mysqldump',
            '--host='.$connection['host'],
            '--port='.(string) ($connection['port'] ?? 3306),
            '--user='.$connection['username'],
            '--single-transaction',
            '--routines',
            '--triggers',
            '--databases',
            $connection['database'],
        ]);
        $dump->setEnv(['MYSQL_PWD' => (string) ($connection['password'] ?? '')]);
        $dump->setTimeout(1800);
        $dump->run();

        if (! $dump->isSuccessful()) {
            Log::error('db:backup mysqldump failed', ['stderr' => $dump->getErrorOutput()]);
            $this->error('mysqldump failed: '.$dump->getErrorOutput());

            return self::FAILURE;
        }

        $gz = gzopen($tempPath, 'wb9');
        if ($gz === false) {
            $this->error('No se pudo crear el archivo temporal comprimido.');

            return self::FAILURE;
        }
        gzwrite($gz, $dump->getOutput());
        gzclose($gz);

        try {
            $stream = fopen($tempPath, 'rb');
            if ($stream === false) {
                throw new \RuntimeException('No se pudo abrir el dump temporal.');
            }
            Storage::disk('s3')->writeStream($s3Key, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } catch (\Throwable $e) {
            Log::error('db:backup S3 upload failed', ['error' => $e->getMessage(), 'key' => $s3Key]);
            $this->error('S3 upload failed: '.$e->getMessage());
            @unlink($tempPath);

            return self::FAILURE;
        }

        @unlink($tempPath);

        $sizeMb = round(Storage::disk('s3')->size($s3Key) / 1024 / 1024, 2);
        Log::info('db:backup completed', ['key' => $s3Key, 'size_mb' => $sizeMb]);
        $this->info("Backup uploaded → {$s3Key} ({$sizeMb} MB)");

        $this->purgeOldBackups((int) $this->option('keep-days'));

        return self::SUCCESS;
    }

    private function purgeOldBackups(int $keepDays): void
    {
        if ($keepDays < 1) {
            $this->warn('Skipping prune: --keep-days must be >= 1');

            return;
        }

        $disk = Storage::disk('s3');
        $cutoff = now()->subDays($keepDays)->getTimestamp();
        $deleted = [];

        try {
            $files = $disk->allFiles('backups');
        } catch (\Throwable $e) {
            Log::warning('db:backup prune: failed to list S3 backups', ['error' => $e->getMessage()]);
            $this->warn('Prune skipped: could not list backups/ on S3 — '.$e->getMessage());

            return;
        }

        foreach ($files as $path) {
            $base = basename($path);
            if (! str_starts_with($base, 'db_backup_')) {
                continue;
            }
            if (! str_ends_with($base, '.sql') && ! str_ends_with($base, '.sql.gz')) {
                continue;
            }

            try {
                if ($disk->lastModified($path) >= $cutoff) {
                    continue;
                }

                $disk->delete($path);
                $deleted[] = $path;
            } catch (\Throwable $e) {
                Log::warning('db:backup prune: failed to delete old backup', [
                    'key' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($deleted === []) {
            $this->info("Prune: no backups older than {$keepDays} days");

            return;
        }

        Log::info('db:backup prune completed', [
            'keep_days' => $keepDays,
            'deleted_count' => count($deleted),
            'deleted' => $deleted,
        ]);
        $this->info('Prune: deleted '.count($deleted)." backup(s) older than {$keepDays} days");
    }
}
