<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft deletes en recursos editables del convite para poder restaurar
 * desde BD tras un borrado accidental (puntos de acopio, proveedores,
 * ítems "qué se necesita", avances). `centros` ya tenía soft deletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iniciativa_puntos_acopio', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('iniciativa_proveedores', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('iniciativa_items', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('iniciativa_avances', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('iniciativa_avances', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('iniciativa_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('iniciativa_proveedores', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('iniciativa_puntos_acopio', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
