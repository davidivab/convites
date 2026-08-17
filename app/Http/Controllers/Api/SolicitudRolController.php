<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoSolicitudRol;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSolicitudRolRequest;
use App\Http\Resources\SolicitudRolResource;
use App\Models\SolicitudRol;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Ciudadano: solicitar rol (moderador/voluntario) y ver el estado de las propias.
 * P46.
 */
class SolicitudRolController extends Controller
{
    public function store(StoreSolicitudRolRequest $request): JsonResponse
    {
        $data = $request->validated();

        $solicitud = SolicitudRol::query()->create([
            'user_id' => $request->user()->id,
            'rol' => $data['rol'],
            'mensaje' => $data['mensaje'] ?? null,
            'estado' => EstadoSolicitudRol::Pendiente,
        ]);

        $solicitud->municipios()->sync($data['municipio_ids']);

        return (new SolicitudRolResource($solicitud->load('municipios')))
            ->response()
            ->setStatusCode(201);
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        $solicitudes = SolicitudRol::query()
            ->where('user_id', $request->user()->id)
            ->with('municipios')
            ->latest()
            ->get();

        return SolicitudRolResource::collection($solicitudes);
    }
}
