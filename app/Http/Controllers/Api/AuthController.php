<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileFieldRules;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge(
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ],
            ProfileFieldRules::rules(),
        ));

        // [P53] Perfil comunitario opcional: se persiste con create(), pero
        // habilidad_ids/disponibilidad_ids no son columnas de `users` — se
        // sincronizan aparte como pivots (igual que ProfileController::update).
        $user = User::query()->create(
            collect($validated)->except(['habilidad_ids', 'disponibilidad_ids'])->all(),
        );
        $user->assignRole('member');

        if (array_key_exists('habilidad_ids', $validated)) {
            $user->habilidades()->sync($validated['habilidad_ids'] ?? []);
        }

        if (array_key_exists('disponibilidad_ids', $validated)) {
            $user->disponibilidades()->sync($validated['disponibilidad_ids'] ?? []);
        }

        SendWelcomeEmailJob::dispatch($user);

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['El correo o la contraseña no son correctos.'],
            ]);
        }

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out']);
    }

    /**
     * @return array{id: int, name: string, email: string, roles: list<string>, permissions: list<string>, municipio_ids: list<int>, needs_onboarding: bool}
     */
    private function userPayload(User $user): array
    {
        $user->loadMissing(['municipiosAsignados', 'habilidades', 'disponibilidades']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'municipio_ids' => $user->assignedMunicipioIds(),
            'needs_onboarding' => $user->needsOnboarding(),
        ];
    }
}
