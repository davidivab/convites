<?php

namespace App\Services;

use App\Enums\EstadoAporte;
use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use Illuminate\Support\Facades\DB;

/**
 * Mantiene consistentes los contadores denormalizados de una iniciativa.
 *
 * Fuente de verdad:
 * - aporte_items + aportes.estado ∈ {confirmado, cumplido} → cantidad_aportada
 * - aportes.asiste_al_convite + estado activo → asistentes_count
 * - promedio de progreso de ítems → progreso_cache
 *
 * Usar SIEMPRE este servicio (o jobs que lo llamen) tras crear/cancelar/cumplir aportes.
 * Evita race conditions con lockForUpdate sobre la iniciativa.
 */
class IniciativaProgresoService
{
    /**
     * Recalcula y persiste todos los caches de una iniciativa.
     */
    public function recalcular(Iniciativa $iniciativa): Iniciativa
    {
        return DB::transaction(function () use ($iniciativa) {
            /** @var Iniciativa $locked */
            $locked = Iniciativa::query()
                ->whereKey($iniciativa->id)
                ->lockForUpdate()
                ->firstOrFail();

            $estadosActivos = [
                EstadoAporte::Confirmado->value,
                EstadoAporte::Cumplido->value,
            ];

            $items = IniciativaItem::query()
                ->where('iniciativa_id', $locked->id)
                ->lockForUpdate()
                ->get();

            $sumas = collect();
            if ($items->isNotEmpty()) {
                $sumas = DB::table('aporte_items')
                    ->join('aportes', 'aportes.id', '=', 'aporte_items.aporte_id')
                    ->whereIn('aporte_items.iniciativa_item_id', $items->pluck('id'))
                    ->whereIn('aportes.estado', $estadosActivos)
                    ->groupBy('aporte_items.iniciativa_item_id')
                    ->selectRaw('aporte_items.iniciativa_item_id, SUM(aporte_items.cantidad) as total')
                    ->pluck('total', 'iniciativa_item_id');
            }

            foreach ($items as $item) {
                $item->forceFill([
                    'cantidad_aportada' => (int) ($sumas[$item->id] ?? 0),
                    'version' => $item->version + 1,
                ])->save();
            }

            $asistentes = (int) DB::table('aportes')
                ->where('iniciativa_id', $locked->id)
                ->whereIn('estado', $estadosActivos)
                ->where('asiste_al_convite', true)
                ->count();

            $locked->load('items');
            $progreso = $locked->items->isEmpty()
                ? 0
                : (int) round($locked->items->avg(fn (IniciativaItem $i) => $i->progresoPorcentaje()));

            $locked->forceFill([
                'asistentes_count' => $asistentes,
                'progreso_cache' => $progreso,
                'version' => $locked->version + 1,
            ])->save();

            return $locked->refresh();
        });
    }

    /**
     * Recalcula todas las iniciativas no borradas (cron / mantenimiento).
     *
     * @return int cantidad procesada
     */
    public function recalcularTodas(): int
    {
        $count = 0;

        Iniciativa::query()
            ->orderBy('id')
            ->chunkById(100, function ($iniciativas) use (&$count) {
                foreach ($iniciativas as $iniciativa) {
                    $this->recalcular($iniciativa);
                    $count++;
                }
            });

        return $count;
    }
}
