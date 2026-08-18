<?php

namespace App\Console\Commands;

use Druidfi\Mysqldump\Mysqldump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Dump MySQL → S3 y purga backups viejos (patrón comfy_back_v2, adaptado).
 *
 * Usa `druidfi/mysqldump-php` (dump 100% en PHP vía PDO) en lugar del
 * binario `mysqldump` del sistema: el cliente MariaDB de la imagen Alpine
 * no trae el plugin `caching_sha2_password` (`/usr/lib/mariadb/plugin/`
 * queda vacío en este paquete), así que `mysqldump`/`mysql` CLI fallan con
 * "Plugin caching_sha2_password could not be loaded" contra cualquier
 * MySQL 8+/9+ que use ese plugin (el default desde MySQL 8.0). La app ya
 * conecta bien porque PHP usa mysqlnd (soporta el plugin nativamente) — el
 * binario CLI es una librería aparte que no lo trae. Verificado: sin este
 * cambio, `db:backup` fallaba en cada corrida programada.
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

        try {
            $dump = new Mysqldump(
                sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    $connection['host'],
                    (string) ($connection['port'] ?? 3306),
                    $connection['database'],
                ),
                (string) $connection['username'],
                (string) ($connection['password'] ?? ''),
                [
                    'compress' => 'Gzip',
                    'single-transaction' => true,
                    'lock-tables' => false,
                    'routines' => true,
                    'add-drop-table' => true,
                    'databases' => true,
                ],
            );
            $dump->start($tempPath);
        } catch (\Throwable $e) {
            Log::error('db:backup mysqldump-php failed', ['error' => $e->getMessage()]);
            $this->error('Dump failed: '.$e->getMessage());
            @unlink($tempPath);

            return self::FAILURE;
        }

        if (! is_file($tempPath) || filesize($tempPath) === 0) {
            Log::error('db:backup produced an empty or missing dump file', ['path' => $tempPath]);
            $this->error('Dump file is empty or missing — aborting before upload.');
            @unlink($tempPath);

            return self::FAILURE;
        }

        try {
            $stream = fopen($tempPath, 'rb');
            if ($stream === false) {
                throw new \RuntimeException('No se pudo abrir el dump temporal.');
            }
            // 'private': este disco S3 también sirve uploads públicos de usuarios
            // (config/filesystems.php: 'visibility' => 'public') — un dump de la
            // base de datos NUNCA debe quedar accesible públicamente.
            Storage::disk('s3')->writeStream($s3Key, $stream, ['visibility' => 'private']);
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
