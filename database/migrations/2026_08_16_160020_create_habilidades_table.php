<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de habilidades que una persona puede ofrecer en un convite.
 * tipo = manual | conocimiento (ver App\Enums\TipoHabilidad).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habilidades', function (Blueprint $table) {
            $table->id();

            $table->string('slug', 120)->unique();
            $table->string('nombre', 160);

            // manual | conocimiento
            $table->string('tipo', 32);

            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habilidades');
    }
};
