<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ítems necesarios de una iniciativa (meta vs aportado).
 *
 * cantidad_aportada es un CACHE: se recalcula desde aporte_items
 * de aportes en estado confirmado|cumplido. Evita sumar en cada listado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iniciativa_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('iniciativa_id')
                ->constrained('iniciativas')
                ->cascadeOnDelete();

            $table->string('nombre', 160);
            $table->string('unidad', 40); // unid., bultos, galones, porciones...

            // Meta solicitada por la comunidad
            $table->unsignedInteger('cantidad_meta');

            // Suma cacheada de aportes activos
            $table->unsignedInteger('cantidad_aportada')->default(0);

            $table->unsignedSmallInteger('orden')->default(0);

            $table->timestamps();

            $table->index(['iniciativa_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iniciativa_items');
    }
};
