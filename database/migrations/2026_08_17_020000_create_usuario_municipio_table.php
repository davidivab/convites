<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asignación N:M usuario ↔ municipio (moderadores y voluntarios).
 * Una sola pivote genérica en vez de duplicar por rol.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_municipio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'municipio_id']);
            $table->index('municipio_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_municipio');
    }
};
