<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avances de convite (P54) — reportes de progreso, generales o por ítem.
 *
 * `porcentaje` es narrativo (documenta el avance reportado), nunca muta
 * `iniciativa_items.cantidad_aportada`/`iniciativas.progreso_cache`. El piso
 * monotónico se calcula en `IniciativaAvance::floorPublicado()` (D-C), no se
 * cachea aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iniciativa_avances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('iniciativa_id')
                ->constrained('iniciativas')
                ->cascadeOnDelete();

            $table->foreignId('iniciativa_item_id')
                ->nullable()
                ->constrained('iniciativa_items')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('slug', 180);
            $table->string('titulo', 200);
            $table->text('cuerpo')->nullable();
            $table->unsignedTinyInteger('porcentaje')->nullable();
            $table->string('enlace_externo', 500)->nullable();
            $table->boolean('notificar_aportantes')->default(false);
            $table->timestamp('notificado_at')->nullable();
            $table->timestamp('publicado_at')->nullable();

            $table->timestamps();

            $table->unique(['iniciativa_id', 'slug']);
            $table->index(['iniciativa_id', 'publicado_at']);
            $table->index(['iniciativa_item_id', 'publicado_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iniciativa_avances');
    }
};
