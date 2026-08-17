<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de acciones de moderación sobre iniciativas.
 * No reemplaza el estado actual; deja auditoría de quién hizo qué.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderacion_acciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('iniciativa_id')
                ->constrained('iniciativas')
                ->cascadeOnDelete();

            // Quién ejecutó la acción (null = sistema / creador)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('accion', 40); // App\Enums\AccionModeracion
            $table->string('estado_anterior', 32)->nullable();
            $table->string('estado_nuevo', 32)->nullable();
            $table->text('nota')->nullable();

            $table->timestamps();

            $table->index(['iniciativa_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderacion_acciones');
    }
};
