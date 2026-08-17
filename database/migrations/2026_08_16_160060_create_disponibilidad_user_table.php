<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot: franjas de disponibilidad del usuario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disponibilidad_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('disponibilidad_id')->constrained('disponibilidades')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'disponibilidad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disponibilidad_user');
    }
};
