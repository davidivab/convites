<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot: habilidades que el usuario declara poder aportar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habilidad_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('habilidad_id')->constrained('habilidades')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'habilidad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habilidad_user');
    }
};
