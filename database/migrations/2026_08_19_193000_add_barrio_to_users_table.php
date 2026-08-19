<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [P53] Perfil extendido: `barrio` (texto libre, opcional) — complementa
 * `municipio_id` en el perfil comunitario. Aditiva, sin tocar filas
 * existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('barrio', 255)->nullable()->after('municipio_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('barrio');
        });
    }
};
