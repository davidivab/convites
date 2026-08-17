<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P45 — URL externa opcional (p. ej. portal de censo de afectaciones).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            $table->string('url_externa', 500)->nullable()->after('direccion');
        });
    }

    public function down(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            $table->dropColumn('url_externa');
        });
    }
};
