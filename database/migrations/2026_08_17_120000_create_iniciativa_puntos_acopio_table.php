<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Puntos de acopio / recolección por iniciativa (P33).
 *
 * El municipio de la iniciativa es el destino del convite; cada punto puede
 * vivir en otra ciudad (ej. Chocó + acopio en Bogotá y Medellín).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iniciativa_puntos_acopio', function (Blueprint $table) {
            $table->id();

            $table->foreignId('iniciativa_id')
                ->constrained('iniciativas')
                ->cascadeOnDelete();

            $table->foreignId('municipio_id')
                ->constrained('municipios')
                ->restrictOnDelete();

            // Opcional: vincular un centro del catálogo global
            $table->foreignId('centro_id')
                ->nullable()
                ->constrained('centros')
                ->nullOnDelete();

            $table->string('nombre', 160);
            $table->string('direccion', 255);
            $table->string('horario', 180)->nullable();
            $table->string('contacto', 120)->nullable();
            $table->string('notas', 500)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);

            $table->timestamps();

            $table->index(['iniciativa_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iniciativa_puntos_acopio');
    }
};
