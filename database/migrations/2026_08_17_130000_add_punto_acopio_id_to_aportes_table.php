<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aportes', function (Blueprint $table) {
            $table->foreignId('punto_acopio_id')
                ->nullable()
                ->after('iniciativa_id')
                ->constrained('iniciativa_puntos_acopio')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('aportes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('punto_acopio_id');
        });
    }
};
