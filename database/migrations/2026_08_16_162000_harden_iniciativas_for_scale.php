<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Endurece iniciativas para producción:
 * - Verificación privada (no pública)
 * - Fecha límite de aportes vs día del convite
 * - Lugar público vs exacto (privacidad)
 * - Cache de progreso + destacadas
 * - Índices de listados / colas
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iniciativas', function (Blueprint $table) {
            // --- Privacidad de ubicación ---
            // lugar_convite queda como texto público (barrio / salón).
            // lugar_exacto solo se revela a quienes confirmaron aporte.
            $table->string('lugar_exacto', 255)
                ->nullable()
                ->after('lugar_convite');

            // --- Fechas duales (wizard v0) ---
            // fecha_convite = día de trabajo comunitario (puede ser null = por definir)
            // fecha_limite_aportes = hasta cuándo se reciben compromisos
            $table->date('fecha_limite_aportes')
                ->nullable()
                ->after('fecha_convite');

            // --- Verificación / moderación (NO se publican) ---
            $table->string('persona_responsable', 160)
                ->nullable()
                ->after('acepta_descargo_at');
            $table->string('quien_respalda', 160)
                ->nullable()
                ->after('persona_responsable');
            $table->string('telefono_contacto', 40)
                ->nullable()
                ->after('quien_respalda');

            // --- Escala / listados ---
            // Promedio 0–100 cacheado (evita recalcular en explorar)
            $table->unsignedTinyInteger('progreso_cache')
                ->default(0)
                ->after('asistentes_count');

            // Home / destacadas editoriales
            $table->boolean('destacada')
                ->default(false)
                ->after('progreso_cache');
            $table->unsignedSmallInteger('orden_destacada')
                ->default(0)
                ->after('destacada');

            // Optimistic lock ligero para contadores concurrentes
            $table->unsignedInteger('version')
                ->default(1)
                ->after('orden_destacada');

            $table->index(['estado', 'publicada_at'], 'iniciativas_estado_publicada_idx');
            $table->index(['user_id', 'estado'], 'iniciativas_user_estado_idx');
            $table->index(['destacada', 'orden_destacada'], 'iniciativas_destacada_idx');
            $table->index('fecha_limite_aportes');
        });

        Schema::table('iniciativa_items', function (Blueprint $table) {
            // Version para updates concurrentes de cantidad_aportada
            $table->unsignedInteger('version')->default(1)->after('orden');
        });

        Schema::table('aportes', function (Blueprint $table) {
            // Idempotencia de creación desde el front (UUID del cliente)
            $table->uuid('client_request_id')->nullable()->after('nota');
            $table->unique('client_request_id');
            $table->index(['user_id', 'estado'], 'aportes_user_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::table('aportes', function (Blueprint $table) {
            $table->dropUnique(['client_request_id']);
            $table->dropIndex('aportes_user_estado_idx');
            $table->dropColumn('client_request_id');
        });

        Schema::table('iniciativa_items', function (Blueprint $table) {
            $table->dropColumn('version');
        });

        Schema::table('iniciativas', function (Blueprint $table) {
            $table->dropIndex('iniciativas_estado_publicada_idx');
            $table->dropIndex('iniciativas_user_estado_idx');
            $table->dropIndex('iniciativas_destacada_idx');
            $table->dropIndex(['fecha_limite_aportes']);
            $table->dropColumn([
                'lugar_exacto',
                'fecha_limite_aportes',
                'persona_responsable',
                'quien_respalda',
                'telefono_contacto',
                'progreso_cache',
                'destacada',
                'orden_destacada',
                'version',
            ]);
        });
    }
};
