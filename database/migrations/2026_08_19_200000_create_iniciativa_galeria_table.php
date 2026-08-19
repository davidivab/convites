<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Galería de imágenes de una iniciativa (P53, parte 3).
 *
 * `path` es relativo al disco de uploads (UploadDisk::name()), igual que
 * `imagen_path` en `iniciativas` — se resuelve a URL absoluta en el resource.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iniciativa_galeria', function (Blueprint $table) {
            $table->id();

            $table->foreignId('iniciativa_id')
                ->constrained('iniciativas')
                ->cascadeOnDelete();

            $table->string('path', 255);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->unsignedInteger('ancho')->nullable();
            $table->unsignedInteger('alto')->nullable();

            $table->timestamps();

            $table->index(['iniciativa_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iniciativa_galeria');
    }
};
