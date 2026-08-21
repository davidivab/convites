<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega soporte de video a `iniciativa_galeria`, mirror de
 * `iniciativa_avance_media` (P54). Migración ADITIVA: la tabla ya existe
 * con filas, por eso `tipo` lleva `default('imagen')` en vez de backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iniciativa_galeria', function (Blueprint $table) {
            $table->string('tipo', 10)->default('imagen')->after('path');
            $table->unsignedSmallInteger('duracion_segundos')->nullable()->after('alto');
        });
    }

    public function down(): void
    {
        Schema::table('iniciativa_galeria', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'duracion_segundos']);
        });
    }
};
