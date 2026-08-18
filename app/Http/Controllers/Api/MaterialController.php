<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoIniciativa;
use App\Http\Controllers\Controller;
use App\Http\Resources\MaterialResource;
use App\Models\IniciativaItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * "¿Tengo este material, quién lo necesita?" (sugerencia de Patricia,
 * 2026-08-17): en vez de explorar convite por convite, lista los ítems
 * que aún faltan entre todas las iniciativas publicadas — mismos filtros
 * geográficos que /api/iniciativas para que el front reutilice el mismo
 * selector de zona/municipio/departamento/categoría/urgencia.
 */
class MaterialController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = IniciativaItem::query()
            ->with(['iniciativa.municipio.departamento', 'iniciativa.categoria'])
            ->whereColumn('cantidad_aportada', '<', 'cantidad_meta')
            ->whereHas('iniciativa', function ($q) use ($request): void {
                $q->whereIn('estado', [
                    EstadoIniciativa::Publicada->value,
                    EstadoIniciativa::EnCurso->value,
                ]);

                if ($request->filled('zona')) {
                    $q->whereHas('zona', fn ($zq) => $zq->where('slug', $request->string('zona')));
                }

                if ($request->filled('municipio')) {
                    $q->whereHas('municipio', fn ($mq) => $mq->where('slug', $request->string('municipio')));
                }

                if ($request->filled('departamento')) {
                    $q->whereHas(
                        'municipio.departamento',
                        fn ($dq) => $dq->where('slug', $request->string('departamento')),
                    );
                }

                if ($request->filled('categoria')) {
                    $q->whereHas('categoria', fn ($cq) => $cq->where('slug', $request->string('categoria')));
                }

                if ($request->filled('urgencia')) {
                    $q->where('urgencia', $request->string('urgencia'));
                }
            });

        if ($request->filled('q')) {
            $query->where('nombre', 'like', '%'.$request->string('q').'%');
        }

        $query->orderBy('nombre');

        return MaterialResource::collection(
            $query->paginate(min(50, max(1, (int) $request->input('per_page', 12))))
        );
    }
}
