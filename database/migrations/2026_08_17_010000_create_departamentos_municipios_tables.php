<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo geográfico Colombia (departamentos → municipios).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique();
            $table->string('nombre', 160);
            $table->string('slug', 180)->unique();
            $table->string('codigo', 10)->nullable();
            $table->boolean('activo')->default(false);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['activo', 'orden']);
        });

        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_id')->constrained('departamentos')->restrictOnDelete();
            $table->unsignedBigInteger('external_id')->unique();
            $table->string('nombre', 160);
            $table->string('slug', 180);
            $table->boolean('activo')->default(false);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['departamento_id', 'slug']);
            $table->index(['departamento_id', 'activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipios');
        Schema::dropIfExists('departamentos');
    }
};
