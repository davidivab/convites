<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * municipio_id en entidades geo + aportes anónimo/evidencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iniciativas', function (Blueprint $table) {
            $table->foreignId('municipio_id')
                ->nullable()
                ->after('zona_id')
                ->constrained('municipios')
                ->nullOnDelete();
        });

        // zona_id pasa a opcional (el front nuevo usa municipio_id).
        Schema::table('iniciativas', function (Blueprint $table) {
            $table->dropForeign(['zona_id']);
        });
        DB::statement('ALTER TABLE iniciativas MODIFY zona_id BIGINT UNSIGNED NULL');
        Schema::table('iniciativas', function (Blueprint $table) {
            $table->foreign('zona_id')->references('id')->on('zonas')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('municipio_id')
                ->nullable()
                ->after('zona_id')
                ->constrained('municipios')
                ->nullOnDelete();
        });

        Schema::table('centros', function (Blueprint $table) {
            $table->foreignId('municipio_id')
                ->nullable()
                ->after('zona_id')
                ->constrained('municipios')
                ->nullOnDelete();
        });

        Schema::table('profesionales', function (Blueprint $table) {
            $table->foreignId('municipio_id')
                ->nullable()
                ->after('zona_id')
                ->constrained('municipios')
                ->nullOnDelete();
        });

        Schema::table('profesional_solicitudes', function (Blueprint $table) {
            $table->foreignId('municipio_id')
                ->nullable()
                ->after('zona_id')
                ->constrained('municipios')
                ->nullOnDelete();
        });

        Schema::table('aportes', function (Blueprint $table) {
            $table->boolean('anonimo')->default(false)->after('nota');
            $table->string('evidencia_disk', 32)->nullable()->after('cumplido_at');
            $table->string('evidencia_path', 500)->nullable()->after('evidencia_disk');
            $table->string('evidencia_nombre_original', 255)->nullable()->after('evidencia_path');
            $table->string('evidencia_mime', 120)->nullable()->after('evidencia_nombre_original');
            $table->unsignedInteger('evidencia_tamanio_bytes')->nullable()->after('evidencia_mime');
        });
    }

    public function down(): void
    {
        Schema::table('aportes', function (Blueprint $table) {
            $table->dropColumn([
                'anonimo',
                'evidencia_disk',
                'evidencia_path',
                'evidencia_nombre_original',
                'evidencia_mime',
                'evidencia_tamanio_bytes',
            ]);
        });

        foreach (['profesional_solicitudes', 'profesionales', 'centros', 'users', 'iniciativas'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('municipio_id');
            });
        }

        Schema::table('iniciativas', function (Blueprint $table) {
            $table->dropForeign(['zona_id']);
        });
        DB::statement('ALTER TABLE iniciativas MODIFY zona_id BIGINT UNSIGNED NOT NULL');
        Schema::table('iniciativas', function (Blueprint $table) {
            $table->foreign('zona_id')->references('id')->on('zonas')->restrictOnDelete();
        });
    }
};
