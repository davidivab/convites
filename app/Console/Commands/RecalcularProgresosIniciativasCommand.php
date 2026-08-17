<?php

namespace App\Console\Commands;

use App\Services\IniciativaProgresoService;
use Illuminate\Console\Command;

/**
 * Cron de consistencia: recalcula contadores denormalizados de iniciativas.
 *
 * Uso:
 *   php artisan convites:recalcular-progresos
 *
 * Programado en routes/console.php (horario nocturno / cada hora según carga).
 */
class RecalcularProgresosIniciativasCommand extends Command
{
    protected $signature = 'convites:recalcular-progresos';

    protected $description = 'Recalcula cantidad_aportada, asistentes_count y progreso_cache de todas las iniciativas';

    public function handle(IniciativaProgresoService $service): int
    {
        $this->info('Recalculando progresos de iniciativas…');

        $total = $service->recalcularTodas();

        $this->info("Listo: {$total} iniciativas actualizadas.");

        return self::SUCCESS;
    }
}
