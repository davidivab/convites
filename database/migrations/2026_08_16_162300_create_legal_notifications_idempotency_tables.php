<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos legales versionados + aceptaciones auditables.
 * Preferencias de notificación del usuario.
 * Idempotency keys para operaciones críticas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_legales', function (Blueprint $table) {
            $table->id();

            // terminos | descargo | privacidad
            $table->string('tipo', 40);
            $table->string('version', 40);
            $table->string('titulo', 200);
            $table->longText('contenido');
            $table->timestamp('publicado_at')->nullable();
            $table->boolean('vigente')->default(false);

            $table->timestamps();

            $table->unique(['tipo', 'version']);
            $table->index(['tipo', 'vigente']);
        });

        Schema::create('documento_legal_aceptaciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('documento_legal_id')
                ->constrained('documentos_legales')
                ->restrictOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('contexto', 80)->nullable(); // registro | crear_iniciativa | profesional
            $table->timestamp('aceptado_at');

            $table->timestamps();

            $table->unique(['user_id', 'documento_legal_id', 'contexto'], 'legal_accept_unique');
        });

        Schema::create('notificacion_preferencias', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();

            // Matching: avisos de convites cerca / por habilidad
            $table->boolean('email_matching')->default(true);
            $table->boolean('email_aportes')->default(true);
            $table->boolean('email_moderacion')->default(true);
            $table->boolean('email_profesionales')->default(true);
            $table->boolean('email_digest_semanal')->default(false);

            // Canal futuro (WhatsApp / push) — apagado por defecto
            $table->boolean('whatsapp_habilitado')->default(false);

            $table->timestamps();
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('key', 64);
            $table->string('route', 120);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['user_id', 'key']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('notificacion_preferencias');
        Schema::dropIfExists('documento_legal_aceptaciones');
        Schema::dropIfExists('documentos_legales');
    }
};
