<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos / certificados adjuntos al perfil profesional.
 * Archivos viven en storage (disk local o S3); aquí solo metadatos + path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profesional_documentos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('profesional_id')
                ->constrained('profesionales')
                ->cascadeOnDelete();

            $table->string('disk', 40)->default('local');
            $table->string('path', 255);
            $table->string('nombre_original', 255);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('tamanio_bytes')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profesional_documentos');
    }
};
