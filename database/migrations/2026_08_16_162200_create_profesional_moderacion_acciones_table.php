<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de moderación de perfiles profesionales
 * (espejo de moderacion_acciones, dominio separado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profesional_moderacion_acciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('profesional_id')
                ->constrained('profesionales')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // aprobar | rechazar | solicitar_cambios | reenviar
            $table->string('accion', 40);
            $table->string('estado_anterior', 32)->nullable();
            $table->string('estado_nuevo', 32)->nullable();
            $table->text('nota')->nullable();

            $table->timestamps();

            $table->index(['profesional_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profesional_moderacion_acciones');
    }
};
