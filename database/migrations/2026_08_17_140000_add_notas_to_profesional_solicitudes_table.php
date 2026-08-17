<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profesional_solicitudes', function (Blueprint $table) {
            $table->text('notas')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('profesional_solicitudes', function (Blueprint $table) {
            $table->dropColumn('notas');
        });
    }
};
