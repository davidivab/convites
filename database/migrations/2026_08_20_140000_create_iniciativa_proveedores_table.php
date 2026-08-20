<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proveedores / contactos de pago-entrega asociados a una iniciativa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iniciativa_proveedores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('iniciativa_id')
                ->constrained('iniciativas')
                ->cascadeOnDelete();

            $table->string('nombre', 160);
            $table->string('direccion', 255)->nullable();
            $table->string('ciudad', 120)->nullable();
            $table->string('correo', 180)->nullable();
            $table->string('celular', 40)->nullable();
            $table->string('instrucciones_pago', 1000);
            $table->unsignedSmallInteger('orden')->default(0);

            $table->timestamps();

            $table->index(['iniciativa_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iniciativa_proveedores');
    }
};
