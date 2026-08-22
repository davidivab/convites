<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auth del bot WhatsApp / MCP: Bearer CONVITES_BOT_TOKEN (no Sanctum).
 */
class AuthenticateBotToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.bot.token', '');
        $provided = (string) ($request->bearerToken() ?? '');

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'No autorizado.',
            ], 401);
        }

        return $next($request);
    }
}
