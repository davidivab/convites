<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoSolicitudRol;
use App\Http\Controllers\Controller;
use App\Http\Resources\SolicitudRolResource;
use App\Models\SolicitudRol;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Admin: aprobar/rechazar solicitudes de rol (moderador/voluntario). P46.
 */
class AdminSolicitudRolController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SolicitudRol::query()->with(['municipios', 'user'])->latest();

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        } else {
            $query->where('estado', EstadoSolicitudRol::Pendiente);
        }

        if ($request->filled('rol')) {
            $query->where('rol', $request->string('rol'));
        }

        return SolicitudRolResource::collection($query->get());
    }

    public function aprobar(Request $request, SolicitudRol $solicitud): SolicitudRolResource
    {
        abort_unless($solicitud->estado === EstadoSolicitudRol::Pendiente, 422, 'Esta solicitud ya fue revisada.');

        $solicitud->forceFill([
            'estado' => EstadoSolicitudRol::Aprobada,
            'revisado_por' => $request->user()->id,
            'revisado_at' => now(),
        ])->save();

        $user = $solicitud->user;
        if (! $user->hasRole($solicitud->rol->rolSpatie())) {
            $user->assignRole($solicitud->rol->rolSpatie());
        }
        $user->municipiosAsignados()->syncWithoutDetaching(
            $solicitud->municipios()->pluck('municipios.id')->all(),
        );

        return new SolicitudRolResource($solicitud->fresh(['municipios', 'user']));
    }

    public function rechazar(Request $request, SolicitudRol $solicitud): SolicitudRolResource
    {
        abort_unless($solicitud->estado === EstadoSolicitudRol::Pendiente, 422, 'Esta solicitud ya fue revisada.');

        $data = $request->validate([
            'nota_revision' => ['required', 'string', 'max:1000'],
        ]);

        $solicitud->forceFill([
            'estado' => EstadoSolicitudRol::Rechazada,
            'nota_revision' => $data['nota_revision'],
            'revisado_por' => $request->user()->id,
            'revisado_at' => now(),
        ])->save();

        return new SolicitudRolResource($solicitud->fresh(['municipios', 'user']));
    }
}
