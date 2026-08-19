<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * [P52] Activa el catálogo geo completo de Colombia.
 *
 * Hasta ahora solo Risaralda, Chocó y Valle del Cauca (y sus municipios)
 * tenían `activo = true`; el resto del catálogo (33 departamentos / 1122
 * municipios, ya migrados/sembrados en BD desde
 * database/data/colombia-geo.json) quedaba fuera del catálogo público sin
 * `?incluir_inactivos=1`. El producto ahora opera a nivel nacional, así
 * que ese subset legacy ya no aplica.
 *
 * Prod-safe a propósito:
 * - Solo hace UPDATE idempotente de la columna `activo`. Correrla más de
 *   una vez no tiene efecto adicional (WHERE activo = false).
 * - NO trunca, NO borra, NO llama a ColombiaGeoSeeder ni a db:seed.
 * - NO toca ninguna otra tabla (users, iniciativas, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('departamentos')->where('activo', false)->update(['activo' => true]);
        DB::table('municipios')->where('activo', false)->update(['activo' => true]);
    }

    /**
     * No-op documentado a propósito.
     *
     * Una vez que todos los flags quedan en `true`, ya no hay forma segura
     * de saber cuáles departamentos/municipios pertenecían al subset legacy
     * (Risaralda, Chocó, Valle del Cauca) sin reintroducir el hardcode que
     * este ticket busca eliminar. Revertir el subset legacy manualmente si
     * alguna vez hace falta; este down() no toca datos.
     */
    public function down(): void
    {
        // Intencional: ver docblock de la clase.
    }
};
