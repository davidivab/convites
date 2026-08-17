<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfiles de "Manos profesionales" (voluntariado especializado gratuito).
 * Requiere revisión documental antes de aparecer en el directorio público.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profesionales', function (Blueprint $table) {
            $table->id();

            // Usuario vinculado cuando ya tiene cuenta; null si se registró solo como profesional
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('zona_id')
                ->constrained('zonas')
                ->restrictOnDelete();

            $table->string('area', 32); // App\Enums\AreaProfesional
            $table->string('nombre', 160);
            $table->string('titulo', 160); // ej. "Psicóloga clínica"
            $table->string('email', 190);
            $table->string('celular', 40)->nullable();
            $table->string('tarjeta_profesional', 80)->nullable();

            $table->string('modalidad', 32); // App\Enums\ModalidadProfesional
            $table->string('disponibilidad', 160);
            $table->text('descripcion');
            $table->string('inicial', 4)->nullable();

            $table->string('estado', 32)->default('pendiente'); // App\Enums\EstadoProfesional
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('aprobado_at')->nullable();
            $table->foreignId('revisado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('nota_revision')->nullable();

            $table->timestamp('acepta_terminos_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['area', 'estado']);
            $table->index('zona_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profesionales');
    }
};
