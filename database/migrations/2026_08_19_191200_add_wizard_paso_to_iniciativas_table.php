<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [P53] Wizard de creación por pasos: paso actual del borrador (1-6).
 * Nullable — filas existentes quedan sin dato, sin backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iniciativas', function (Blueprint $table) {
            $table->unsignedTinyInteger('wizard_paso')->nullable()->after('version');
        });
    }

    public function down(): void
    {
        Schema::table('iniciativas', function (Blueprint $table) {
            $table->dropColumn('wizard_paso');
        });
    }
};
