<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * P53 (parte 4): validación progresiva por wizard_paso.
 *
 * `IniciativaPayloadRules` ya no exige `lugar_convite` en autosaves de
 * pasos previos al paso 2 ("Ubicación y fechas"), pero la columna seguía
 * NOT NULL desde la migración original — un autosave legítimo del paso 1
 * fallaba con un 500 en el INSERT. Mismo patrón que
 * 2026_08_17_010100 (zona_id) y 2026_08_18_180000 (profesionales.zona_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE iniciativas MODIFY lugar_convite VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE iniciativas MODIFY lugar_convite VARCHAR(255) NOT NULL');
    }
};
