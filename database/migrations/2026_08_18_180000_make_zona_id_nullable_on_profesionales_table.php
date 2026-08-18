<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bug real encontrado en producción (2026-08-18): `profesionales.zona_id`
 * seguía NOT NULL sin default aunque `RegisterProfesionalRequest` permite
 * registrarse solo con `municipio_id` (`required_without:zona_id`) — el
 * front actual solo manda `municipio_id`, nunca `zona_id`. Cada registro
 * de profesional real fallaba con un 500 en el INSERT. La migración
 * 2026_08_17_010100 ya había hecho este mismo cambio para `iniciativas`
 * pero se olvidó de `profesionales` — este es el mismo patrón, aplicado acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profesionales', function (Blueprint $table) {
            $table->dropForeign(['zona_id']);
        });
        DB::statement('ALTER TABLE profesionales MODIFY zona_id BIGINT UNSIGNED NULL');
        Schema::table('profesionales', function (Blueprint $table) {
            $table->foreign('zona_id')->references('id')->on('zonas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('profesionales', function (Blueprint $table) {
            $table->dropForeign(['zona_id']);
        });
        DB::statement('ALTER TABLE profesionales MODIFY zona_id BIGINT UNSIGNED NOT NULL');
        Schema::table('profesionales', function (Blueprint $table) {
            $table->foreign('zona_id')->references('id')->on('zonas')->restrictOnDelete();
        });
    }
};
