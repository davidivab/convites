<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compromiso de un usuario con una iniciativa ("Voy a llevar" / asisto).
 *
 * Un aporte puede:
 * - incluir líneas en aporte_items (materiales)
 * - y/o solo marcar asiste_al_convite = true
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aportes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('iniciativa_id')->constrained('iniciativas')->cascadeOnDelete();

            // confirmado | cancelado | cumplido
            $table->string('estado', 32)->default('confirmado');

            // El vecino planea presentarse el día del convite
            $table->boolean('asiste_al_convite')->default(true);

            $table->text('nota')->nullable();

            $table->timestamp('confirmado_at')->nullable();
            $table->timestamp('cancelado_at')->nullable();
            $table->timestamp('cumplido_at')->nullable();

            $table->timestamps();

            // Un usuario activo solo debería tener un compromiso vigente por iniciativa
            $table->unique(['user_id', 'iniciativa_id']);
            $table->index(['iniciativa_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aportes');
    }
};
