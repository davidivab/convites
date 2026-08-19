<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Perfil del usuario autenticado (datos para aportar / panel).
 */
class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load(['zona', 'municipio.departamento', 'habilidades', 'disponibilidades']);

        return response()->json(['data' => $this->payload($user)]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validated();

        $user->fill(collect($validated)->except(['habilidad_ids', 'disponibilidad_ids'])->all());
        $user->save();

        if (array_key_exists('habilidad_ids', $validated)) {
            $user->habilidades()->sync($validated['habilidad_ids'] ?? []);
        }

        if (array_key_exists('disponibilidad_ids', $validated)) {
            $user->disponibilidades()->sync($validated['disponibilidad_ids'] ?? []);
        }

        $user->load(['zona', 'municipio.departamento', 'habilidades', 'disponibilidades']);

        return response()->json(['data' => $this->payload($user)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'celular' => $user->celular,
            'zona_id' => $user->zona_id,
            'zona' => $user->zona
                ? [
                    'id' => $user->zona->id,
                    'slug' => $user->zona->slug,
                    'nombre' => $user->zona->nombre,
                ]
                : null,
            'municipio_id' => $user->municipio_id,
            'municipio' => $user->municipio
                ? [
                    'id' => $user->municipio->id,
                    'slug' => $user->municipio->slug,
                    'nombre' => $user->municipio->nombre,
                    'departamento' => $user->municipio->departamento
                        ? [
                            'id' => $user->municipio->departamento->id,
                            'slug' => $user->municipio->departamento->slug,
                            'nombre' => $user->municipio->departamento->nombre,
                        ]
                        : null,
                ]
                : null,
            'barrio' => $user->barrio,
            'genero' => $user->genero?->value,
            'edad' => $user->edad,
            'aptitud_fisica' => $user->aptitud_fisica?->value,
            'notas_salud' => $user->notas_salud,
            'inicial' => $user->inicial,
            'habilidades' => $user->habilidades->map(fn ($h) => [
                'id' => $h->id,
                'nombre' => $h->nombre,
                'tipo' => $h->tipo?->value ?? $h->tipo,
            ])->values()->all(),
            'disponibilidades' => $user->disponibilidades->map(fn ($d) => [
                'id' => $d->id,
                'nombre' => $d->nombre,
            ])->values()->all(),
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'needs_onboarding' => $user->needsOnboarding(),
        ];
    }
}
