<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evidencia propia del aportante (independiente de la evidencia del
 * organizador, ligada a la máquina de estados de recepción).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aportes', function (Blueprint $table) {
            $table->string('evidencia_aportante_disk', 32)->nullable()->after('evidencia_tamanio_bytes');
            $table->string('evidencia_aportante_path', 500)->nullable()->after('evidencia_aportante_disk');
            $table->string('evidencia_aportante_nombre_original', 255)->nullable()->after('evidencia_aportante_path');
            $table->string('evidencia_aportante_mime', 120)->nullable()->after('evidencia_aportante_nombre_original');
            $table->unsignedInteger('evidencia_aportante_tamanio_bytes')->nullable()->after('evidencia_aportante_mime');
        });
    }

    public function down(): void
    {
        Schema::table('aportes', function (Blueprint $table) {
            $table->dropColumn([
                'evidencia_aportante_disk',
                'evidencia_aportante_path',
                'evidencia_aportante_nombre_original',
                'evidencia_aportante_mime',
                'evidencia_aportante_tamanio_bytes',
            ]);
        });
    }
};
