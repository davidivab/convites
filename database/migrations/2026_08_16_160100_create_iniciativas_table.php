<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Iniciativas / convites comunitarios.
 *
 * Convites NO recibe dinero: enlace_externo_* es opcional y apunta a terceros (Vaki, etc.).
 * El progreso de materiales vive en iniciativa_items.cantidad_aportada (cache).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iniciativas', function (Blueprint $table) {
            $table->id();

            // Organizador (usuario creador)
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('zona_id')
                ->constrained('zonas')
                ->restrictOnDelete();

            $table->foreignId('categoria_id')
                ->constrained('categorias')
                ->restrictOnDelete();

            // Identidad pública
            $table->string('slug', 180)->unique();
            $table->string('titulo', 200);
            $table->string('resumen', 500);
            // Párrafos de la historia (array JSON de strings)
            $table->json('historia');

            // Clasificación
            $table->string('urgencia', 16); // App\Enums\Urgencia
            $table->string('estado', 32)->default('borrador'); // App\Enums\EstadoIniciativa

            // Media
            $table->string('imagen_path', 255)->nullable();

            // Cuándo / dónde es el convite
            // fecha_convite: valor ordenable; null = "por definir"
            $table->date('fecha_convite')->nullable();
            // Texto legible mostrado al usuario (ej. "Sábado 6 de septiembre, 7:00 a.m.")
            $table->string('fecha_convite_texto', 160)->nullable();
            $table->string('lugar_convite', 255);

            // Enlace opcional a recaudación externa (Convites no administra el dinero)
            $table->string('enlace_externo_plataforma', 80)->nullable();
            $table->string('enlace_externo_url', 500)->nullable();

            // Contador denormalizado de personas que confirmaron asistencia
            $table->unsignedInteger('asistentes_count')->default(0);

            // Moderación
            $table->timestamp('enviada_revision_at')->nullable();
            $table->timestamp('publicada_at')->nullable();
            $table->timestamp('cerrada_at')->nullable();
            $table->foreignId('moderada_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('nota_moderacion')->nullable();

            // Consentimientos del creador al enviar a revisión
            $table->timestamp('acepta_terminos_at')->nullable();
            $table->timestamp('acepta_descargo_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['estado', 'urgencia']);
            $table->index(['zona_id', 'estado']);
            $table->index('fecha_convite');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iniciativas');
    }
};
