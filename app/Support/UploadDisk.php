<?php

namespace App\Support;

/**
 * Disco configurado para uploads de usuario (S3 en prod, public en local).
 */
final class UploadDisk
{
    public static function name(): string
    {
        return (string) config('filesystems.upload', 'public');
    }
}
