<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas de material comprometidas dentro de un aporte.
 * Al confirmar/cancelar se actualiza iniciativa_items.cantidad_aportada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aporte_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('aporte_id')
                ->constrained('aportes')
                ->cascadeOnDelete();

            $table->foreignId('iniciativa_item_id')
                ->constrained('iniciativa_items')
                ->cascadeOnDelete();

            $table->unsignedInteger('cantidad');

            $table->timestamps();

            $table->unique(['aporte_id', 'iniciativa_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aporte_items');
    }
};
