<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccionModeracionProfesional;
use App\Enums\EstadoProfesional;
use App\Enums\EstadoSolicitudProfesional;
use App\Enums\PreferenciaContacto;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterProfesionalRequest;
use App\Http\Requests\UpdateMiPerfilProfesionalRequest;
use App\Http\Requests\UpdateSolicitudProfesionalRequest;
use App\Http\Resources\ProfesionalResource;
use App\Http\Resources\ProfesionalSolicitudResource;
use App\Models\Profesional;
use App\Models\ProfesionalModeracionAccion;
use App\Models\ProfesionalSolicitud;
use App\Support\UploadDisk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Manos profesionales: directorio, registro y solicitudes de contacto.
 */
class ProfesionalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Profesional::query()
            ->with('zona')
            ->where('estado', EstadoProfesional::Aprobado)
            ->orderBy('nombre');

        if ($request->filled('area')) {
            $query->where('area', $request->string('area'));
        }

        if ($request->filled('zona')) {
            $query->whereHas('zona', fn ($q) => $q->where('slug', $request->string('zona')));
        }

        return ProfesionalResource::collection($query->paginate(24));
    }

    public function show(Profesional $profesional): ProfesionalResource
    {
        abort_unless($profesional->estado === EstadoProfesional::Aprobado, 404);

        return new ProfesionalResource($profesional->load(['zona', 'documentos']));
    }

    public function register(RegisterProfesionalRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->profesional()->exists()) {
            throw ValidationException::withMessages([
                'profesional' => ['Ya tienes un perfil profesional registrado.'],
            ]);
        }

        $data = $request->validated();
        $documentos = $data['documentos'] ?? [];
        unset($data['documentos']);

        $profesional = Profesional::query()->create([
            ...$data,
            'user_id' => $user->id,
            'inicial' => Str::upper(Str::substr($data['nombre'], 0, 1)),
            'estado' => EstadoProfesional::Pendiente,
            'enviado_at' => now(),
            'acepta_terminos_at' => now(),
        ]);

        // P31: certificados opcionales — mismo disco de uploads que evidencias (P16/P23).
        $disk = UploadDisk::name();
        foreach ($documentos as $documento) {
            $path = $documento->store('profesionales/documentos', $disk);
            $profesional->documentos()->create([
                'disk' => $disk,
                'path' => $path,
                'nombre_original' => $documento->getClientOriginalName(),
                'mime' => $documento->getClientMimeType(),
                'tamanio_bytes' => $documento->getSize(),
                'checksum' => hash_file('sha256', $documento->getRealPath()) ?: null,
                'uploaded_by' => $user->id,
            ]);
        }

        // P46: el rol `profesional` ya NO se asigna acá — recién cuando se
        // aprueba el perfil (ver moderar()). Antes de eso el registro queda
        // pendiente de revisión, igual que moderador/voluntario.

        return (new ProfesionalResource($profesional->load(['zona', 'documentos'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * P29 — el propio profesional gestiona su perfil (no las acciones de moderación).
     */
    public function miPerfil(Request $request): ProfesionalResource
    {
        $profesional = $request->user()->profesional()->with(['zona', 'documentos'])->first();
        abort_unless($profesional, 404);

        return new ProfesionalResource($profesional);
    }

    public function actualizarMiPerfil(UpdateMiPerfilProfesionalRequest $request): ProfesionalResource
    {
        $profesional = $request->user()->profesional;
        abort_unless($profesional, 404);

        $profesional->update($request->validated());

        return new ProfesionalResource($profesional->fresh('zona'));
    }

    public function misSolicitudes(Request $request): AnonymousResourceCollection
    {
        $profesional = $request->user()->profesional;
        abort_unless($profesional, 404);

        return ProfesionalSolicitudResource::collection(
            $profesional->solicitudes()->latest()->paginate(20),
        );
    }

    /**
     * P44: el profesional cambia el estado operativo de una solicitud propia
     * y va acumulando notas (no las reemplaza — se agregan con fecha).
     */
    public function actualizarSolicitud(
        UpdateSolicitudProfesionalRequest $request,
        ProfesionalSolicitud $solicitud,
    ): ProfesionalSolicitudResource {
        $profesional = $request->user()->profesional;
        abort_unless($profesional && $solicitud->profesional_id === $profesional->id, 404);

        $data = $request->validated();

        if (array_key_exists('estado', $data)) {
            $solicitud->estado = $data['estado'];
        }

        if (! empty($data['nota'])) {
            $linea = now()->format('Y-m-d H:i').' — '.$data['nota'];
            $solicitud->notas = $solicitud->notas
                ? $solicitud->notas."\n".$linea
                : $linea;
        }

        $solicitud->save();

        return new ProfesionalSolicitudResource($solicitud);
    }

    public function contact(Request $request, Profesional $profesional): JsonResponse
    {
        abort_unless($profesional->estado === EstadoProfesional::Aprobado, 404);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'celular' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'zona_id' => ['nullable', 'integer', 'exists:zonas,id'],
            'municipio_id' => ['nullable', 'integer', 'exists:municipios,id'],
            'preferencia_contacto' => ['required', Rule::enum(PreferenciaContacto::class)],
            'mensaje' => ['required', 'string', 'max:2000'],
        ]);

        $solicitud = ProfesionalSolicitud::query()->create([
            ...$data,
            'profesional_id' => $profesional->id,
            'user_id' => $request->user()?->id,
            'estado' => EstadoSolicitudProfesional::Pendiente,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ]);

        return response()->json([
            'message' => 'Solicitud enviada. El profesional te contactará pronto.',
            'data' => [
                'id' => $solicitud->id,
                'estado' => $solicitud->estado->value,
            ],
        ], 201);
    }

    public function moderationQueue(Request $request): AnonymousResourceCollection
    {
        $estado = $request->input('estado', EstadoProfesional::Pendiente->value);

        $query = Profesional::query()
            ->with('zona')
            ->orderBy('enviado_at');

        if ($estado !== 'todas') {
            $query->where('estado', $estado);
        }

        return ProfesionalResource::collection($query->paginate(20));
    }

    public function aprobar(Request $request, Profesional $profesional): ProfesionalResource
    {
        return $this->moderar(
            $request,
            $profesional,
            AccionModeracionProfesional::Aprobar,
            EstadoProfesional::Aprobado,
            ['aprobado_at' => now()],
        );
    }

    public function rechazar(Request $request, Profesional $profesional): ProfesionalResource
    {
        $data = $request->validate([
            'nota' => ['required', 'string', 'max:1000'],
        ]);

        return $this->moderar(
            $request,
            $profesional,
            AccionModeracionProfesional::Rechazar,
            EstadoProfesional::Rechazado,
            ['nota_revision' => $data['nota']],
        );
    }

    public function solicitarCambios(Request $request, Profesional $profesional): ProfesionalResource
    {
        $data = $request->validate([
            'nota' => ['required', 'string', 'max:1000'],
        ]);

        return $this->moderar(
            $request,
            $profesional,
            AccionModeracionProfesional::SolicitarCambios,
            EstadoProfesional::CambiosSolicitados,
            ['nota_revision' => $data['nota']],
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function moderar(
        Request $request,
        Profesional $profesional,
        AccionModeracionProfesional $accion,
        EstadoProfesional $nuevo,
        array $extra = [],
    ): ProfesionalResource {
        if (! in_array($profesional->estado, [
            EstadoProfesional::Pendiente,
            EstadoProfesional::CambiosSolicitados,
        ], true)) {
            abort(422, 'Este perfil no está en cola de revisión.');
        }

        $anterior = $profesional->estado;

        $profesional->forceFill(array_merge([
            'estado' => $nuevo,
            'revisado_por' => $request->user()->id,
        ], $extra))->save();

        ProfesionalModeracionAccion::query()->create([
            'profesional_id' => $profesional->id,
            'user_id' => $request->user()->id,
            'accion' => $accion,
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'nota' => $extra['nota_revision'] ?? null,
        ]);

        // P46: el rol se otorga recién al aprobar (no antes, no en rechazo/cambios).
        if ($nuevo === EstadoProfesional::Aprobado && $profesional->user_id) {
            $profesional->user?->assignRole('profesional');
        }

        return new ProfesionalResource($profesional->fresh('zona'));
    }
}
