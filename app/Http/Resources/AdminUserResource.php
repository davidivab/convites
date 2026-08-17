<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AdminUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'celular' => $user->celular,
            'inicial' => $user->inicial,
            'roles' => $user->getRoleNames()->values()->all(),
            'municipios' => $user->relationLoaded('municipiosAsignados')
                ? $user->municipiosAsignados->map(fn ($m) => [
                    'id' => $m->id,
                    'nombre' => $m->nombre,
                    'slug' => $m->slug,
                    'departamento' => $m->relationLoaded('departamento') && $m->departamento
                        ? [
                            'id' => $m->departamento->id,
                            'nombre' => $m->departamento->nombre,
                            'slug' => $m->departamento->slug,
                        ]
                        : null,
                ])->values()->all()
                : [],
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
