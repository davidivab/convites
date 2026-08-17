<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende users con el perfil comunitario del front v0
 * (registro, perfil editor, matching con convites).
 *
 * Notas:
 * - El dinero NUNCA se gestiona aquí; solo perfil / contacto / habilidades.
 * - acepta_terminos_at / acepta_descargo_at documentan consentimientos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Contacto
            $table->string('celular', 40)->nullable()->after('email');

            // Zona principal del usuario (puede ser null al registrarse)
            $table->foreignId('zona_id')
                ->nullable()
                ->after('celular')
                ->constrained('zonas')
                ->nullOnDelete();

            // Perfil opcional
            $table->string('genero', 32)->nullable()->after('zona_id'); // App\Enums\Genero
            $table->unsignedTinyInteger('edad')->nullable()->after('genero');
            $table->string('aptitud_fisica', 16)->nullable()->after('edad'); // App\Enums\AptitudFisica
            $table->text('notas_salud')->nullable()->after('aptitud_fisica');
            $table->string('avatar_path', 255)->nullable()->after('notas_salud');
            $table->string('inicial', 4)->nullable()->after('avatar_path');

            // OAuth opcional (Google button del front)
            $table->string('google_id', 64)->nullable()->unique()->after('inicial');

            // Consentimientos obligatorios al registrarse / publicar
            $table->timestamp('acepta_terminos_at')->nullable()->after('google_id');
            $table->timestamp('acepta_descargo_at')->nullable()->after('acepta_terminos_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zona_id');
            $table->dropColumn([
                'celular',
                'genero',
                'edad',
                'aptitud_fisica',
                'notas_salud',
                'avatar_path',
                'inicial',
                'google_id',
                'acepta_terminos_at',
                'acepta_descargo_at',
            ]);
        });
    }
};
