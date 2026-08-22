<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motivo opcional al anular/cancelar un aporte (aportante u organizador)
 * y quién lo canceló, para auditoría en tab aportantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aportes', function (Blueprint $table) {
            $table->text('cancelado_motivo')->nullable()->after('cancelado_at');
            $table->foreignId('cancelado_por_user_id')
                ->nullable()
                ->after('cancelado_motivo')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('aportes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelado_por_user_id');
            $table->dropColumn('cancelado_motivo');
        });
    }
};
