<?php

namespace Database\Seeders;

use App\Enums\TipoDocumentoLegal;
use App\Models\DocumentoLegal;
use App\Models\NotificacionPreferencia;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Documentos legales vigentes (placeholder) + preferencias de notificación
 * para usuarios seed.
 */
class LegalAndNotificationsSeeder extends Seeder
{
    public function run(): void
    {
        $docs = [
            [
                'tipo' => TipoDocumentoLegal::Terminos,
                'version' => '2026.09.01',
                'titulo' => 'Términos y condiciones',
                'contenido' => 'Documento placeholder. Reemplazar con el texto legal definitivo de Convites.',
            ],
            [
                'tipo' => TipoDocumentoLegal::Descargo,
                'version' => '2026.09.01',
                'titulo' => 'Descargo de responsabilidad',
                'contenido' => 'Convites es una plataforma ciudadana. No somos fundación ni empresa. No administramos dinero.',
            ],
            [
                'tipo' => TipoDocumentoLegal::Privacidad,
                'version' => '2026.09.01',
                'titulo' => 'Política de privacidad',
                'contenido' => 'Documento placeholder de privacidad (Ley 1581 de 2012).',
            ],
        ];

        foreach ($docs as $doc) {
            // Solo una versión vigente por tipo
            DocumentoLegal::query()
                ->where('tipo', $doc['tipo'])
                ->update(['vigente' => false]);

            DocumentoLegal::query()->updateOrCreate(
                [
                    'tipo' => $doc['tipo'],
                    'version' => $doc['version'],
                ],
                [
                    'titulo' => $doc['titulo'],
                    'contenido' => $doc['contenido'],
                    'publicado_at' => now(),
                    'vigente' => true,
                ],
            );
        }

        User::query()->each(function (User $user) {
            NotificacionPreferencia::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'email_matching' => true,
                    'email_aportes' => true,
                    'email_moderacion' => true,
                    'email_profesionales' => true,
                    'email_digest_semanal' => false,
                    'whatsapp_habilitado' => false,
                ],
            );
        });
    }
}
