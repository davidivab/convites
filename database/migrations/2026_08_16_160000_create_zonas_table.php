<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de zonas geográficas (Risaralda / Pereira y alrededores).
 * Usado por usuarios, iniciativas, centros y profesionales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas', function (Blueprint $table) {
            $table->id();

            // Clave estable para el front (ej. "dosquebradas", "pereira-villa-santana")
            $table->string('slug', 120)->unique();

            // Nombre visible (ej. "Pereira — Villa Santana")
            $table->string('nombre', 160);

            // Municipio o agrupación superior (opcional, para filtros)
            $table->string('municipio', 120)->nullable();

            // Orden de aparición en selects / listados
            $table->unsignedSmallInteger('orden')->default(0);

            // Soft-disable sin borrar histórico
            $table->boolean('activo')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zonas');
    }
};
