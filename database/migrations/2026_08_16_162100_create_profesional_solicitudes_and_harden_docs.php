<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de contacto a manos profesionales
 * (formulario "Contactar" del directorio v0).
 *
 * Contiene PII del solicitante → retención / rate-limit en capa de aplicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profesional_solicitudes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('profesional_id')
                ->constrained('profesionales')
                ->cascadeOnDelete();

            // Usuario autenticado opcional (puede contactar sin cuenta)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('nombre', 160);
            $table->string('celular', 40);
            $table->string('email', 190)->nullable();
            $table->foreignId('zona_id')
                ->nullable()
                ->constrained('zonas')
                ->nullOnDelete();

            // llamada | whatsapp | correo
            $table->string('preferencia_contacto', 20);

            $table->text('mensaje');

            // pendiente | notificada | respondida | cerrada | spam
            $table->string('estado', 32)->default('pendiente');
            $table->timestamp('notificada_at')->nullable();
            $table->timestamp('leida_at')->nullable();
            $table->timestamp('cerrada_at')->nullable();

            // Anti-abuso
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index(['profesional_id', 'estado']);
            $table->index(['estado', 'created_at']);
        });

        Schema::table('profesionales', function (Blueprint $table) {
            // Un usuario = un perfil profesional (HasOne real)
            $table->unique('user_id');
            $table->index(['estado', 'enviado_at'], 'profesionales_cola_idx');
        });

        Schema::table('profesional_documentos', function (Blueprint $table) {
            $table->string('checksum', 64)->nullable()->after('tamanio_bytes');
            $table->foreignId('uploaded_by')
                ->nullable()
                ->after('checksum')
                ->constrained('users')
                ->nullOnDelete();
            // pendiente | limpio | infectado | error
            $table->string('virus_scan_status', 20)
                ->default('pendiente')
                ->after('uploaded_by');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('profesional_documentos', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('uploaded_by');
            $table->dropColumn(['checksum', 'virus_scan_status']);
        });

        Schema::table('profesionales', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->dropIndex('profesionales_cola_idx');
        });

        Schema::dropIfExists('profesional_solicitudes');
    }
};
