<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoIniciativa;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminEstadisticasRequest;
use App\Models\Iniciativa;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * P51: panel de estadísticas del admin (usuarios/convites por día,
 * distribución por estado y avance global) sobre un rango de fechas.
 */
class AdminEstadisticasController extends Controller
{
    public function index(AdminEstadisticasRequest $request): JsonResponse
    {
        $endDate = $request->filled('end_date')
            ? CarbonImmutable::createFromFormat('Y-m-d', $request->string('end_date')->toString())
            : CarbonImmutable::now();

        $startDate = $request->filled('start_date')
            ? CarbonImmutable::createFromFormat('Y-m-d', $request->string('start_date')->toString())
            : CarbonImmutable::now()->subWeeks(2);

        $usuariosPorDia = $this->zeroFilledCountByDay(User::query(), $startDate, $endDate);
        $convitesPorDia = $this->zeroFilledCountByDay(Iniciativa::query(), $startDate, $endDate);

        // Mismo conjunto para convites_por_estado y avance_global: iniciativas
        // cuyo fecha_convite cae en rango, todos los estados incluidos.
        $iniciativasEnRango = Iniciativa::query()
            ->whereDate('fecha_convite', '>=', $startDate->toDateString())
            ->whereDate('fecha_convite', '<=', $endDate->toDateString())
            ->get(['estado', 'progreso_cache']);

        $convitesPorEstado = collect(EstadoIniciativa::cases())
            ->map(fn (EstadoIniciativa $estado) => [
                'estado' => $estado->value,
                'total' => $iniciativasEnRango->where('estado', $estado)->count(),
            ])
            ->values()
            ->all();

        $convitesConsiderados = $iniciativasEnRango->count();
        $promedio = $convitesConsiderados > 0
            ? (int) round($iniciativasEnRango->avg('progreso_cache'))
            : 0;

        return response()->json([
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'usuarios_por_dia' => $usuariosPorDia,
            'convites_por_dia' => $convitesPorDia,
            'convites_por_estado' => $convitesPorEstado,
            'avance_global' => [
                'promedio' => $promedio,
                'convites_considerados' => $convitesConsiderados,
            ],
        ]);
    }

    /**
     * Cuenta filas de $query por día de created_at dentro de [$startDate,
     * $endDate] (inclusive), zero-filleando en PHP los días sin registros.
     *
     * @return list<array{fecha: string, total: int}>
     */
    private function zeroFilledCountByDay(Builder $query, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        /** @var Collection<string, int> $counts */
        $counts = $query
            ->whereDate('created_at', '>=', $startDate->toDateString())
            ->whereDate('created_at', '<=', $endDate->toDateString())
            ->selectRaw('DATE(created_at) as dia, COUNT(*) as total')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        return collect(CarbonPeriod::create($startDate, $endDate))
            ->map(fn ($fecha) => [
                'fecha' => $fecha->toDateString(),
                'total' => (int) ($counts[$fecha->toDateString()] ?? 0),
            ])
            ->values()
            ->all();
    }
}
