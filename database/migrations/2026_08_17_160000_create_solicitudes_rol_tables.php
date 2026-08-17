<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_rol', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // moderador | voluntario — ver App\Enums\TipoSolicitudRol
            $table->string('rol', 32);

            $table->text('mensaje')->nullable();

            // pendiente | aprobada | rechazada
            $table->string('estado', 16)->default('pendiente');

            $table->text('nota_revision')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'rol']);
            $table->index(['estado', 'rol']);
        });

        Schema::create('solicitud_rol_municipio', function (Blueprint $table) {
            $table->foreignId('solicitud_rol_id')->constrained('solicitudes_rol')->cascadeOnDelete();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->primary(['solicitud_rol_id', 'municipio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_rol_municipio');
        Schema::dropIfExists('solicitudes_rol');
    }
};
