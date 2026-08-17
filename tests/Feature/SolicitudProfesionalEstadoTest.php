<?php

namespace Tests\Feature;

use App\Enums\EstadoProfesional;
use App\Enums\EstadoSolicitudProfesional;
use App\Enums\PreferenciaContacto;
use App\Models\Profesional;
use App\Models\ProfesionalSolicitud;
use App\Models\User;
use App\Models\Zona;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolicitudProfesionalEstadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function crearProfesionalConSolicitud(User $user): array
    {
        $zona = Zona::factory()->create();
        $profesional = Profesional::factory()->create([
            'user_id' => $user->id,
            'zona_id' => $zona->id,
            'estado' => EstadoProfesional::Aprobado,
        ]);
        $user->assignRole('profesional');

        $solicitud = ProfesionalSolicitud::query()->create([
            'profesional_id' => $profesional->id,
            'nombre' => 'Interesado',
            'celular' => '+57 300 000 0000',
            'preferencia_contacto' => PreferenciaContacto::Whatsapp,
            'mensaje' => 'Necesito ayuda.',
            'estado' => EstadoSolicitudProfesional::Pendiente,
        ]);

        return [$profesional, $solicitud];
    }

    public function test_profesional_actualiza_estado_y_agrega_nota(): void
    {
        $user = User::factory()->create();
        [, $solicitud] = $this->crearProfesionalConSolicitud($user);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/mi-perfil-profesional/solicitudes/{$solicitud->id}", [
                'estado' => 'atendida',
                'nota' => 'La contacté por WhatsApp, quedamos en llamar mañana.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'atendida');

        $this->assertStringContainsString(
            'La contacté por WhatsApp, quedamos en llamar mañana.',
            $response->json('data.nota'),
        );

        $this->assertSame(EstadoSolicitudProfesional::Atendida, $solicitud->fresh()->estado);
        $this->assertStringContainsString('La contacté por WhatsApp', $solicitud->fresh()->notas);
    }

    public function test_las_notas_se_van_acumulando(): void
    {
        $user = User::factory()->create();
        [, $solicitud] = $this->crearProfesionalConSolicitud($user);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/mi-perfil-profesional/solicitudes/{$solicitud->id}", ['nota' => 'Primera nota.'])
            ->assertOk();
        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/mi-perfil-profesional/solicitudes/{$solicitud->id}", ['nota' => 'Segunda nota.'])
            ->assertOk();

        $notas = $solicitud->fresh()->notas;
        $this->assertStringContainsString('Primera nota.', $notas);
        $this->assertStringContainsString('Segunda nota.', $notas);
    }

    public function test_rechaza_estado_invalido(): void
    {
        $user = User::factory()->create();
        [, $solicitud] = $this->crearProfesionalConSolicitud($user);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/mi-perfil-profesional/solicitudes/{$solicitud->id}", ['estado' => 'no_existe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estado']);
    }

    public function test_no_puede_actualizar_solicitud_de_otro_profesional(): void
    {
        $userA = User::factory()->create();
        [, $solicitudA] = $this->crearProfesionalConSolicitud($userA);

        $userB = User::factory()->create();
        $this->crearProfesionalConSolicitud($userB);

        $this->actingAs($userB, 'sanctum')
            ->patchJson("/api/mi-perfil-profesional/solicitudes/{$solicitudA->id}", ['estado' => 'negada'])
            ->assertNotFound();

        $this->assertSame(EstadoSolicitudProfesional::Pendiente, $solicitudA->fresh()->estado);
    }
}
