<?php

namespace Tests\Feature;

use App\Enums\EstadoSolicitudRol;
use App\Enums\TipoSolicitudRol;
use App\Jobs\SendSolicitudRolAprobadaJob;
use App\Jobs\SendSolicitudRolRegistradaJob;
use App\Mail\SolicitudRolAprobadaMail;
use App\Mail\SolicitudRolRegistradaMail;
use App\Models\Municipio;
use App\Models\SolicitudRol;
use App\Models\User;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Correos de solicitud de rol (moderador/voluntario): al enviarla y al
 * aprobarla. Mismo código para ambos tipos.
 */
class SolicitudRolNotificacionesEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
    }

    private function municipioActivo(): Municipio
    {
        return Municipio::query()->where('activo', true)->firstOrFail();
    }

    public function test_solicitar_rol_voluntario_encola_correo_de_confirmacion(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $user->assignRole('member');
        $municipio = $this->municipioActivo();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/solicitudes-rol', [
                'rol' => 'voluntario',
                'municipio_ids' => [$municipio->id],
            ])
            ->assertCreated();

        Queue::assertPushed(SendSolicitudRolRegistradaJob::class);
    }

    public function test_job_de_solicitud_registrada_manda_correo_de_confirmacion(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $solicitud = SolicitudRol::query()->create([
            'user_id' => $user->id,
            'rol' => TipoSolicitudRol::Voluntario,
            'estado' => EstadoSolicitudRol::Pendiente,
        ]);

        (new SendSolicitudRolRegistradaJob($solicitud))->handle();

        Mail::assertSent(SolicitudRolRegistradaMail::class, fn ($mail) => $mail->hasTo($user->email)
            && str_contains($mail->render(), 'Voluntario'));
    }

    public function test_aprobar_solicitud_de_moderador_encola_correo(): void
    {
        Queue::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();
        $user->assignRole('member');
        $municipio = $this->municipioActivo();
        $solicitud = SolicitudRol::query()->create([
            'user_id' => $user->id,
            'rol' => TipoSolicitudRol::Moderador,
            'estado' => EstadoSolicitudRol::Pendiente,
        ]);
        $solicitud->municipios()->sync([$municipio->id]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/solicitudes-rol/{$solicitud->id}/aprobar")
            ->assertOk();

        Queue::assertPushed(SendSolicitudRolAprobadaJob::class, fn ($job) => $job->solicitud->is($solicitud));
    }

    public function test_job_de_solicitud_aprobada_manda_correo(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $solicitud = SolicitudRol::query()->create([
            'user_id' => $user->id,
            'rol' => TipoSolicitudRol::Moderador,
            'estado' => EstadoSolicitudRol::Aprobada,
            'revisado_at' => now(),
        ]);

        (new SendSolicitudRolAprobadaJob($solicitud))->handle();

        Mail::assertSent(SolicitudRolAprobadaMail::class, fn ($mail) => $mail->hasTo($user->email)
            && str_contains($mail->render(), 'Moderador'));
    }
}
