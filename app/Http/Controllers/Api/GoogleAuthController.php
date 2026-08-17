<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * P42: login/registro con Google.
 *
 * Flujo (BFF): front pide `redirect` → manda al navegador a Google → Google
 * vuelve acá (`callback`, NUNCA al front — el client secret vive solo acá) →
 * generamos un código de intercambio de un solo uso y corta vida, y recién
 * ahí redirigimos al navegador al front con ese código en la URL (nunca el
 * token real, para no dejarlo en el historial/logs) → el front lo canjea
 * server-to-server contra `exchange`.
 */
class GoogleAuthController extends Controller
{
    private const EXCHANGE_TTL_SECONDS = 60;

    public function redirect(): JsonResponse
    {
        $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    public function callback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        $user = User::query()->where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::query()->where('email', $googleUser->getEmail())->first();

            if ($user) {
                // Cuenta existente (password) con el mismo email de Google: se vincula.
                $user->forceFill(['google_id' => $googleUser->getId()])->save();
            } else {
                $user = User::query()->create([
                    'name' => $googleUser->getName() ?: $googleUser->getEmail(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'password' => null,
                    'inicial' => Str::upper(Str::substr($googleUser->getName() ?: $googleUser->getEmail(), 0, 1)),
                    'acepta_terminos_at' => now(),
                    'acepta_descargo_at' => now(),
                    'email_verified_at' => now(),
                ]);
                $user->assignRole('member');
            }
        }

        $token = $user->createToken('google-oauth')->plainTextToken;
        $exchangeCode = Str::random(48);

        Cache::put("google-auth-exchange:{$exchangeCode}", $token, self::EXCHANGE_TTL_SECONDS);

        $frontendUrl = config('services.google.frontend_callback_url');

        return redirect()->away($frontendUrl.'?code='.$exchangeCode);
    }

    public function exchange(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $cacheKey = "google-auth-exchange:{$data['code']}";
        $token = Cache::get($cacheKey);

        abort_unless($token, 404, 'Código de intercambio inválido o expirado.');

        Cache::forget($cacheKey);

        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        abort_unless($accessToken, 404);

        $user = $accessToken->tokenable;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->values()->all(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            ],
        ]);
    }
}
