<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaces adicionales de una iniciativa (P53, parte 3).
 *
 * Distinto de `enlace_externo_plataforma`/`enlace_externo_url` (columnas ya
 * existentes en `iniciativas` para un único enlace externo): esto es una
 * lista de enlaces (hasta 20) con reemplazo total en cada create/update,
 * igual que `iniciativa_items`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iniciativa_enlaces', function (Blueprint $table) {
            $table->id();

            $table->foreignId('iniciativa_id')
                ->constrained('iniciativas')
                ->cascadeOnDelete();

            $table->string('titulo', 160);
            $table->string('url', 500);
            $table->unsignedSmallInteger('orden')->default(0);

            $table->timestamps();

            $table->index(['iniciativa_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iniciativa_enlaces');
    }
};
