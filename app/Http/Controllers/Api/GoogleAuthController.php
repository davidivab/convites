<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileFieldRules;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * P42/P47: login/registro con Google.
 *
 * Flujo (BFF): front pide `redirect` → manda al navegador a Google → Google
 * vuelve acá (`callback`, NUNCA al front — el client secret vive solo acá).
 *
 * - Si el email/google_id YA tiene cuenta: login directo, código de
 *   intercambio de un solo uso vía `exchange` (comportamiento sin cambios).
 * - Si NO existe cuenta (venga de `/ingresar` o `/registrarse`): NO se crea
 *   nada todavía (P47) — se guarda el perfil de Google en un código
 *   "pendiente de registro" y se redirige al front con `needs_registration=1`
 *   para que complete los datos obligatorios en `/registrarse` y llame a
 *   `completar-registro`.
 */
class GoogleAuthController extends Controller
{
    private const EXCHANGE_TTL_SECONDS = 60;
    private const PENDING_REGISTRATION_TTL_SECONDS = 600;

    public function redirect(Request $request): JsonResponse
    {
        $intent = $request->query('intent') === 'register' ? 'register' : 'login';

        $url = Socialite::driver('google')
            ->stateless()
            ->with(['state' => $intent])
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->stateless()->user();
        $frontendUrl = config('services.google.frontend_callback_url');

        $user = User::query()->where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::query()->where('email', $googleUser->getEmail())->first();

            if ($user) {
                // Cuenta existente (password) con el mismo email de Google: se vincula.
                $user->forceFill(['google_id' => $googleUser->getId()])->save();
            }
        }

        if ($user) {
            $token = $user->createToken('google-oauth')->plainTextToken;
            $exchangeCode = Str::random(48);
            Cache::put("google-auth-exchange:{$exchangeCode}", $token, self::EXCHANGE_TTL_SECONDS);

            return redirect()->away($frontendUrl.'?code='.$exchangeCode);
        }

        // P47: no existe cuenta — no se crea acá. Se guarda el perfil de
        // Google verificado y se manda a completar el registro.
        $pendingCode = Str::random(48);
        Cache::put("google-pending:{$pendingCode}", [
            'google_id' => $googleUser->getId(),
            'email' => $googleUser->getEmail(),
            'name' => $googleUser->getName() ?: $googleUser->getEmail(),
        ], self::PENDING_REGISTRATION_TTL_SECONDS);

        return redirect()->away($frontendUrl.'?code='.$pendingCode.'&needs_registration=1');
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

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($accessToken->tokenable),
        ]);
    }

    /**
     * P47: crea la cuenta recién acá, con los datos obligatorios que faltaban
     * (celular, aceptaciones legales) — el perfil de Google ya viene verificado
     * (email, nombre, google_id), no hace falta volver a pedir password.
     */
    public function completarRegistro(Request $request): JsonResponse
    {
        $data = $request->validate(array_merge(
            ProfileFieldRules::rules(),
            [
                'code' => ['required', 'string'],
                'acepta_terminos' => ['required', 'accepted'],
                'acepta_descargo' => ['required', 'accepted'],
            ],
        ));

        $cacheKey = "google-pending:{$data['code']}";
        $pending = Cache::get($cacheKey);

        abort_unless($pending, 404, 'Código de registro inválido o expirado.');

        Cache::forget($cacheKey);

        // [P53] Perfil comunitario opcional (mismos campos que
        // AuthController::register) — habilidad_ids/disponibilidad_ids no
        // son columnas de `users`, se sincronizan aparte como pivots.
        $profileFields = collect($data)
            ->except(['code', 'acepta_terminos', 'acepta_descargo', 'habilidad_ids', 'disponibilidad_ids'])
            ->all();

        $user = User::query()->create(array_merge($profileFields, [
            'name' => $pending['name'],
            'email' => $pending['email'],
            'google_id' => $pending['google_id'],
            'password' => null,
            'inicial' => Str::upper(Str::substr($pending['name'], 0, 1)),
        ]));
        // acepta_terminos_at/acepta_descargo_at/email_verified_at no son
        // fillable a propósito (no deben setearse por mass-assignment desde
        // un request arbitrario) — se asignan acá explícitamente.
        $user->forceFill([
            'acepta_terminos_at' => now(),
            'acepta_descargo_at' => now(),
            'email_verified_at' => now(),
        ])->save();
        $user->assignRole('member');

        if (array_key_exists('habilidad_ids', $data)) {
            $user->habilidades()->sync($data['habilidad_ids'] ?? []);
        }

        if (array_key_exists('disponibilidad_ids', $data)) {
            $user->disponibilidades()->sync($data['disponibilidad_ids'] ?? []);
        }

        SendWelcomeEmailJob::dispatch($user);

        $token = $user->createToken('google-oauth')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $user->loadMissing(['habilidades', 'disponibilidades']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'needs_onboarding' => $user->needsOnboarding(),
        ];
    }
}
