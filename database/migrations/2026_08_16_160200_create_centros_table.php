<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centros de interés (acopio, albergue, bomberos, hospital, policía, defensa civil).
 * Catálogo editorial / operativo; no es crowdfunding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centros', function (Blueprint $table) {
            $table->id();

            $table->string('tipo', 32); // App\Enums\TipoCentro
            $table->string('nombre', 200);

            $table->foreignId('zona_id')
                ->constrained('zonas')
                ->restrictOnDelete();

            $table->string('direccion', 255);
            $table->string('telefono', 60)->nullable();
            $table->string('horario', 160)->nullable();

            $table->string('estado', 16)->default('abierto'); // App\Enums\EstadoCentro
            $table->text('descripcion');

            // Listas libres para centros de acopio
            $table->json('necesita')->nullable();
            $table->json('no_recibe')->nullable();

            // Capacidad de albergues
            $table->unsignedInteger('capacidad_total')->nullable();
            $table->unsignedInteger('capacidad_ocupada')->nullable();

            // Servicios de emergencia 24h
            $table->boolean('emergencia')->default(false);

            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tipo', 'estado']);
            $table->index('zona_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centros');
    }
};
