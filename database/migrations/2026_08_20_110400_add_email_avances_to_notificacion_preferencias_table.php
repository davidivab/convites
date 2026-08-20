<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P54 (avances-convite): opt-out preference for the aportante notification
 * email. Defaults to true — a missing preference row is treated as
 * opted-in by `SendAvanceAportantesJob` via `?? true` (D-B), so this
 * default only matters for rows that already exist or get created going
 * forward; no backfill of existing rows is performed (D-B).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificacion_preferencias', function (Blueprint $table) {
            $table->boolean('email_avances')->default(true)->after('email_aportes');
        });
    }

    public function down(): void
    {
        Schema::table('notificacion_preferencias', function (Blueprint $table) {
            $table->dropColumn('email_avances');
        });
    }
};
