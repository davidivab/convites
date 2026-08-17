<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AporteResource;
use App\Http\Resources\IniciativaResource;
use App\Models\Aporte;
use App\Models\Iniciativa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Lectura admin sin scope de municipio (auditoría).
 */
class AdminIniciativaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Iniciativa::query()
            ->with(['zona', 'municipio.departamento', 'categoria', 'creador', 'items'])
            ->orderByDesc('updated_at');

        if ($request->filled('estado') && $request->string('estado') !== 'todas') {
            $query->where('estado', $request->string('estado'));
        }

        if ($request->filled('municipio_id')) {
            $query->where('municipio_id', (int) $request->input('municipio_id'));
        }

        if ($request->filled('categoria')) {
            $query->whereHas('categoria', fn ($q) => $q->where('slug', $request->string('categoria')));
        }

        if ($request->filled('urgencia')) {
            $query->where('urgencia', $request->string('urgencia'));
        }

        if ($request->filled('desde')) {
            $query->whereDate('created_at', '>=', $request->string('desde'));
        }

        if ($request->filled('hasta')) {
            $query->whereDate('created_at', '<=', $request->string('hasta'));
        }

        if ($request->filled('q')) {
            $term = '%'.trim((string) $request->string('q')).'%';
            $query->where(function ($builder) use ($term) {
                $builder->where('titulo', 'like', $term)
                    ->orWhere('resumen', 'like', $term)
                    ->orWhere('slug', 'like', $term);
            });
        }

        return IniciativaResource::collection(
            $query->paginate(min(50, max(1, (int) $request->input('per_page', 20)))),
        );
    }

    public function show(string $slug): JsonResponse
    {
        $iniciativa = Iniciativa::query()
            ->with([
                'zona',
                'municipio.departamento',
                'categoria',
                'creador',
                'items',
                'moderacionAcciones.user',
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        $payload = (new IniciativaResource($iniciativa))->resolve();

        $payload['verificacion'] = [
            'persona_responsable' => $iniciativa->persona_responsable,
            'quien_respalda' => $iniciativa->quien_respalda,
            'telefono_contacto' => $iniciativa->telefono_contacto,
            'lugar_exacto' => $iniciativa->lugar_exacto,
        ];

        $payload['moderacion_historial'] = $iniciativa->moderacionAcciones
            ->sortBy('created_at')
            ->values()
            ->map(fn ($accion) => [
                'id' => $accion->id,
                'accion' => $accion->accion?->value,
                'estado_anterior' => $accion->estado_anterior?->value,
                'estado_nuevo' => $accion->estado_nuevo?->value,
                'nota' => $accion->nota,
                'moderador' => $accion->user
                    ? [
                        'id' => $accion->user->id,
                        'name' => $accion->user->name,
                    ]
                    : null,
                'created_at' => $accion->created_at?->toIso8601String(),
            ])
            ->all();

        return response()->json(['data' => $payload]);
    }

    public function aportes(Request $request, string $slug): AnonymousResourceCollection
    {
        $iniciativa = Iniciativa::query()->where('slug', $slug)->firstOrFail();

        // Flag para que AporteResource muestre nombres anónimos al admin.
        $request->attributes->set('admin_reveal_anonimo', true);

        $aportes = Aporte::query()
            ->with(['items.iniciativaItem', 'user', 'iniciativa'])
            ->where('iniciativa_id', $iniciativa->id)
            ->orderByDesc('created_at')
            ->paginate(50);

        return AporteResource::collection($aportes);
    }
}
