<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detalle opcional de cómo consigo un ítem y su valor monetario aproximado
 * (COP) por unidad. Ambos son estimados libres, nunca obligatorios:
 * - descripcion: instrucciones/detalles de dónde o cómo conseguirlo.
 * - valor_unitario_aprox: usado para calcular (no almacenar) los totales
 *   valor_meta_aprox / valor_aportado_aprox expuestos en la API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iniciativa_items', function (Blueprint $table) {
            $table->text('descripcion')->nullable()->after('unidad');
            $table->decimal('valor_unitario_aprox', 12, 2)->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('iniciativa_items', function (Blueprint $table) {
            $table->dropColumn(['descripcion', 'valor_unitario_aprox']);
        });
    }
};
