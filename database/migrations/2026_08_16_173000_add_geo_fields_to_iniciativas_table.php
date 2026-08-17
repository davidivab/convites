<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ubicación geográfica pública del punto de encuentro del convite.
 * No reemplaza lugar_exacto (texto privado); el pin del mapa es el encuentro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iniciativas', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('lugar_exacto');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
            $table->string('geo_fuente', 20)->nullable()->after('lng'); // gps|busqueda|manual
            $table->string('geo_precision', 20)->default('punto')->after('geo_fuente'); // punto|aproximado
            $table->boolean('mapa_visible')->default(true)->after('geo_precision');

            $table->index(['mapa_visible', 'lat', 'lng'], 'iniciativas_mapa_idx');
        });
    }

    public function down(): void
    {
        Schema::table('iniciativas', function (Blueprint $table) {
            $table->dropIndex('iniciativas_mapa_idx');
            $table->dropColumn(['lat', 'lng', 'geo_fuente', 'geo_precision', 'mapa_visible']);
        });
    }
};
