<?php

namespace App\Console\Commands;

use App\Models\ProfesionalSolicitud;
use Illuminate\Console\Command;

/**
 * Borra solicitudes de contacto a profesionales más viejas que N días.
 *
 * REVIEW: retención 30d — confirmar política con el equipo/legal.
 * Puede hacer falta historial más largo para el profesional, anonimizar
 * en vez de borrar, u otra obligación de retención.
 *
 * Uso:
 *   php artisan convites:purge-profesional-solicitudes
 *   php artisan convites:purge-profesional-solicitudes --days=30
 */
class PurgeProfesionalSolicitudesCommand extends Command
{
    protected $signature = 'convites:purge-profesional-solicitudes
                            {--days=30 : Días de retención (provisional — ver REVIEW)}
                            {--dry-run : Solo contar, no borrar}';

    protected $description = 'Elimina profesional_solicitudes (PII) más antiguas que el período de retención';

    public function handle(): int
    {
        // REVIEW: retención 30d — confirmar política
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $query = ProfesionalSolicitud::query()->where('created_at', '<', $cutoff);
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Dry-run: {$count} solicitud(es) anteriores a {$cutoff->toDateString()} (retención {$days}d).");

            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info("Purgadas {$deleted} solicitud(es) anteriores a {$cutoff->toDateString()} (retención {$days}d).");

        return self::SUCCESS;
    }
}
