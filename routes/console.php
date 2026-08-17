<?php

use App\Console\Commands\PurgeProfesionalSolicitudesCommand;
use App\Console\Commands\RecalcularProgresosIniciativasCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Crons de Convites
|--------------------------------------------------------------------------
|
| - Recalcular progresos: red de seguridad ante drift de contadores.
| - Purga PII de profesional_solicitudes: retención provisional 30 días
|   (REVIEW: confirmar política con el equipo/legal).
| - Limpieza de idempotency_keys: se agregará cuando haya volumen.
| - Matching / digest de notificaciones: cuando existan jobs de notificación.
|
*/

Schedule::command(RecalcularProgresosIniciativasCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command(PurgeProfesionalSolicitudesCommand::class)
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->onOneServer();

require __DIR__.'/schedule/backup.php';
