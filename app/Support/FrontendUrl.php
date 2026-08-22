<?php

namespace App\Support;

/**
 * URLs absolutas del front para el bot (links en WhatsApp).
 */
final class FrontendUrl
{
    public static function base(): string
    {
        $configured = (string) config('services.bot.frontend_url', '');
        if ($configured === '') {
            $configured = (string) env('FRONTEND_URL', 'https://convites.co');
        }

        $first = trim(explode(',', $configured)[0] ?? 'https://convites.co');

        return rtrim($first !== '' ? $first : 'https://convites.co', '/');
    }

    public static function path(string $path): string
    {
        return self::base().'/'.ltrim($path, '/');
    }
}
