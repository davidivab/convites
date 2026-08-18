<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * db:backup sube el dump completo de la base de datos a S3. Nunca debe
 * quedar público — el disco 's3' compartido con uploads de usuarios usa
 * 'visibility' => 'public' por defecto (config/filesystems.php), así que
 * el comando tiene que forzar 'private' explícitamente en cada backup.
 */
class DatabaseBackupCommandTest extends TestCase
{
    public function test_backup_sube_el_dump_como_privado_no_publico(): void
    {
        Storage::fake('s3');

        $this->artisan('db:backup')->assertSuccessful();

        $files = Storage::disk('s3')->allFiles('backups');
        $this->assertNotEmpty($files, 'db:backup no subió ningún archivo a S3.');

        $backup = $files[0];
        $this->assertSame(
            'private',
            Storage::disk('s3')->getVisibility($backup),
            'El dump de la base de datos quedó con visibilidad pública en S3.',
        );
    }

    public function test_backup_purga_solo_dumps_mas_viejos_que_keep_days(): void
    {
        Storage::fake('s3');

        Storage::disk('s3')->put('backups/2020/01/db_backup_2020-01-01_00-00-00.sql.gz', 'viejo');
        $this->touchS3File('backups/2020/01/db_backup_2020-01-01_00-00-00.sql.gz', now()->subDays(30)->getTimestamp());

        Storage::disk('s3')->put('backups/2020/01/no-tocar.txt', 'no es un backup');

        $this->artisan('db:backup', ['--keep-days' => 10])->assertSuccessful();

        $this->assertFalse(Storage::disk('s3')->exists('backups/2020/01/db_backup_2020-01-01_00-00-00.sql.gz'));
        $this->assertTrue(Storage::disk('s3')->exists('backups/2020/01/no-tocar.txt'));
    }

    private function touchS3File(string $path, int $timestamp): void
    {
        $absolute = Storage::disk('s3')->path($path);
        touch($absolute, $timestamp);
    }
}
