<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Búsqueda full-text en título/resumen (MySQL InnoDB).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iniciativas', function (Blueprint $table) {
            $table->fullText(['titulo', 'resumen'], 'iniciativas_titulo_resumen_fulltext');
        });
    }

    public function down(): void
    {
        Schema::table('iniciativas', function (Blueprint $table) {
            $table->dropFullText('iniciativas_titulo_resumen_fulltext');
        });
    }
};
