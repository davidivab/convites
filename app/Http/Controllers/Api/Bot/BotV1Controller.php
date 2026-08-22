<?php

namespace App\Http\Controllers\Api\Bot;

use App\Enums\TipoCentro;
use App\Enums\Urgencia;
use App\Http\Controllers\Controller;
use App\Models\Centro;
use App\Models\Iniciativa;
use App\Support\FrontendUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Fachada de solo lectura para el agente WhatsApp / MCP.
 * Sin PII de aportantes ni datos de profesionales.
 * Solo convites activos: publicada | en_curso.
 */
class BotV1Controller extends Controller
{
    /** @var list<string> */
    private const ESTADOS_ACTIVOS = [
        'publicada',
        'en_curso',
    ];

    public function health(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'service' => 'convites-bot',
        ]);
    }

    public function convites(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'municipio' => ['sometimes', 'nullable', 'string', 'max:120'],
            'departamento' => ['sometimes', 'nullable', 'string', 'max:120'],
            'urgencia' => ['sometimes', 'nullable', Rule::enum(Urgencia::class)],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);

        $limit = (int) ($data['limit'] ?? 5);

        $query = Iniciativa::query()
            ->with(['municipio.departamento', 'items'])
            ->whereIn('estado', self::ESTADOS_ACTIVOS)
            ->orderByDesc('publicada_at')
            ->orderByDesc('id');

        if (! empty($data['q'])) {
            $q = $data['q'];
            $query->where(function (Builder $b) use ($q) {
                $b->where('titulo', 'like', '%'.$q.'%')
                    ->orWhere('resumen', 'like', '%'.$q.'%');
            });
        }

        if (! empty($data['urgencia'])) {
            $query->where('urgencia', $data['urgencia']);
        }

        $this->scopeMunicipioDepartamento(
            $query,
            $data['municipio'] ?? null,
            $data['departamento'] ?? null,
            'municipio',
        );

        $rows = $query->limit($limit)->get()->map(fn (Iniciativa $i) => $this->mapConviteListItem($i));

        return response()->json([
            'data' => $rows,
            'meta' => [
                'count' => $rows->count(),
                'limit' => $limit,
            ],
        ])->header('Cache-Control', 'public, max-age=30');
    }

    public function conviteShow(string $slug): JsonResponse
    {
        $iniciativa = Iniciativa::query()
            ->with([
                'municipio.departamento',
                'items',
                'puntosAcopio.municipio.departamento',
                'proveedores',
                'categoria',
            ])
            ->where('slug', $slug)
            ->whereIn('estado', self::ESTADOS_ACTIVOS)
            ->firstOrFail();

        $items = $iniciativa->items->map(function ($item) {
            $faltante = max(0, (int) $item->cantidad_meta - (int) $item->cantidad_aportada);

            return [
                'nombre' => $item->nombre,
                'unidad' => $item->unidad,
                'cantidad_meta' => (int) $item->cantidad_meta,
                'cantidad_aportada' => (int) $item->cantidad_aportada,
                'faltante' => $faltante,
            ];
        })->values();

        $puntos = $iniciativa->puntosAcopio->map(fn ($p) => [
            'nombre' => $p->nombre,
            'direccion' => $p->direccion,
            'horario' => $p->horario,
            'contacto' => $p->contacto,
            'municipio' => $p->municipio?->nombre,
            'departamento' => $p->municipio?->departamento?->nombre,
        ])->values();

        // Sin correo/celular de proveedores.
        $proveedores = $iniciativa->proveedores->map(fn ($p) => [
            'nombre' => $p->nombre,
            'ciudad' => $p->ciudad,
            'direccion' => $p->direccion,
            'instrucciones_pago' => $p->instrucciones_pago,
        ])->values();

        $historia = $iniciativa->historia;
        if (is_array($historia)) {
            $historia = implode("\n\n", array_filter(array_map('strval', $historia)));
        }

        return response()->json([
            'data' => [
                'slug' => $iniciativa->slug,
                'titulo' => $iniciativa->titulo,
                'resumen' => $iniciativa->resumen,
                'historia' => $historia,
                'urgencia' => $iniciativa->urgencia?->value,
                'progreso' => $iniciativa->progresoTotal(),
                'categoria' => $iniciativa->categoria?->nombre,
                'municipio' => $iniciativa->municipio?->nombre,
                'departamento' => $iniciativa->municipio?->departamento?->nombre,
                'lugar_convite' => $iniciativa->lugar_convite,
                'fecha_convite' => $iniciativa->fecha_convite?->toDateString(),
                'fecha_limite_aportes' => $iniciativa->fecha_limite_aportes?->toDateString(),
                'url' => FrontendUrl::path('iniciativa/'.$iniciativa->slug),
                'items' => $items,
                'puntos_acopio' => $puntos,
                'proveedores' => $proveedores,
            ],
        ])->header('Cache-Control', 'public, max-age=30');
    }

    public function centros(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipo' => ['sometimes', 'nullable', Rule::enum(TipoCentro::class)],
            'municipio' => ['sometimes', 'nullable', 'string', 'max:120'],
            'departamento' => ['sometimes', 'nullable', 'string', 'max:120'],
            'solo_emergencia' => ['sometimes', 'nullable'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:15'],
        ]);

        $limit = (int) ($data['limit'] ?? 8);

        $query = Centro::query()
            ->with(['municipio.departamento', 'zona'])
            ->where('activo', true)
            ->orderByDesc('emergencia')
            ->orderBy('orden');

        if (! empty($data['tipo'])) {
            $query->where('tipo', $data['tipo']);
        }

        if (filter_var($data['solo_emergencia'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('emergencia', true);
        }

        $this->scopeMunicipioDepartamento(
            $query,
            $data['municipio'] ?? null,
            $data['departamento'] ?? null,
            'municipio',
        );

        $rows = $query->limit($limit)->get()->map(fn (Centro $c) => [
            'id' => $c->id,
            'tipo' => $c->tipo?->value,
            'tipo_label' => $c->tipo?->label(),
            'nombre' => $c->nombre,
            'estado' => $c->estado?->value,
            'estado_label' => $c->estado?->label(),
            'direccion' => $c->direccion,
            'horario' => $c->horario,
            'telefono' => $c->telefono,
            'necesita' => $c->necesita,
            'no_recibe' => $c->no_recibe,
            'emergencia' => (bool) $c->emergencia,
            'municipio' => $c->municipio?->nombre,
            'departamento' => $c->municipio?->departamento?->nombre,
            'url' => FrontendUrl::path('centros'),
        ]);

        return response()->json([
            'data' => $rows,
            'meta' => [
                'count' => $rows->count(),
                'limit' => $limit,
            ],
        ])->header('Cache-Control', 'public, max-age=30');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapConviteListItem(Iniciativa $i): array
    {
        $faltantes = $i->items
            ->map(function ($item) {
                $faltante = max(0, (int) $item->cantidad_meta - (int) $item->cantidad_aportada);
                if ($faltante <= 0) {
                    return null;
                }

                return $item->nombre.' '.$faltante.' '.$item->unidad;
            })
            ->filter()
            ->take(4)
            ->implode('; ');

        return [
            'slug' => $i->slug,
            'titulo' => $i->titulo,
            'resumen' => $i->resumen,
            'urgencia' => $i->urgencia?->value,
            'progreso' => $i->progresoTotal(),
            'municipio' => $i->municipio?->nombre,
            'departamento' => $i->municipio?->departamento?->nombre,
            'fecha_convite' => $i->fecha_convite?->toDateString(),
            'url' => FrontendUrl::path('iniciativa/'.$i->slug),
            'faltantes_resumen' => $faltantes !== '' ? $faltantes : null,
        ];
    }

    private function scopeMunicipioDepartamento(
        Builder $query,
        ?string $municipio,
        ?string $departamento,
        string $relation,
    ): void {
        if ($municipio) {
            $m = trim($municipio);
            $query->whereHas($relation, function (Builder $q) use ($m) {
                $q->where('slug', $m)
                    ->orWhere('nombre', 'like', '%'.$m.'%');
            });
        }

        if ($departamento) {
            $d = trim($departamento);
            $query->whereHas($relation.'.departamento', function (Builder $q) use ($d) {
                $q->where('slug', $d)
                    ->orWhere('nombre', 'like', '%'.$d.'%');
            });
        }
    }
}
