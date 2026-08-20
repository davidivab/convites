<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media (imagen|video) de un avance de convite (P54). Mirror de
 * `iniciativa_galeria` (disco `UploadDisk`). `tipo` es string (no enum) —
 * inferido server-side desde el MIME (D-H), nunca desde el cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iniciativa_avance_media', function (Blueprint $table) {
            $table->id();

            $table->foreignId('iniciativa_avance_id')
                ->constrained('iniciativa_avances')
                ->cascadeOnDelete();

            $table->string('path', 255);
            $table->string('tipo', 10);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->unsignedInteger('ancho')->nullable();
            $table->unsignedInteger('alto')->nullable();
            $table->unsignedSmallInteger('duracion_segundos')->nullable();

            $table->timestamps();

            $table->index(['iniciativa_avance_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iniciativa_avance_media');
    }
};
