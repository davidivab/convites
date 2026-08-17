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

class MiPerfilProfesionalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function crearProfesionalPropio(User $user): Profesional
    {
        $zona = Zona::factory()->create();

        return Profesional::factory()->create([
            'user_id' => $user->id,
            'zona_id' => $zona->id,
            'estado' => EstadoProfesional::Aprobado,
        ]);
    }

    public function test_registrar_profesional_no_asigna_el_rol_todavia(): void
    {
        // P46: el rol se otorga recién cuando se aprueba el perfil, no al registrar.
        $user = User::factory()->create();
        $user->assignRole('member');
        $zona = Zona::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/profesionales', [
            'zona_id' => $zona->id,
            'area' => 'psicologia',
            'nombre' => 'Test Profesional',
            'titulo' => 'Psicólogo',
            'email' => 'nuevo.profesional@convites.test',
            'modalidad' => 'virtual',
            'disponibilidad' => 'Tardes',
            'descripcion' => 'Descripción de prueba con suficiente longitud.',
        ])->assertCreated();

        $this->assertFalse($user->fresh()->hasRole('profesional'));
        $this->assertTrue($user->fresh()->hasRole('member'));
    }

    public function test_aprobar_el_perfil_recien_ahi_asigna_el_rol_profesional(): void
    {
        $user = User::factory()->create();
        $user->assignRole('member');
        $zona = Zona::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/profesionales', [
            'zona_id' => $zona->id,
            'area' => 'psicologia',
            'nombre' => 'Test Profesional',
            'titulo' => 'Psicólogo',
            'email' => 'pendiente.aprobacion@convites.test',
            'modalidad' => 'virtual',
            'disponibilidad' => 'Tardes',
            'descripcion' => 'Descripción de prueba con suficiente longitud.',
        ])->assertCreated();

        $this->assertFalse($user->fresh()->hasRole('profesional'));

        $profesional = Profesional::query()->where('email', 'pendiente.aprobacion@convites.test')->firstOrFail();

        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');

        $this->actingAs($moderator, 'sanctum')
            ->postJson("/api/moderacion/profesionales/{$profesional->id}/aprobar")
            ->assertOk();

        $this->assertTrue($user->fresh()->hasRole('profesional'));
        $this->assertTrue($user->fresh()->hasRole('member'));
    }

    public function test_rechazar_el_perfil_no_asigna_el_rol(): void
    {
        $user = User::factory()->create();
        $user->assignRole('member');
        $zona = Zona::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/profesionales', [
            'zona_id' => $zona->id,
            'area' => 'psicologia',
            'nombre' => 'Test Rechazo',
            'titulo' => 'Psicólogo',
            'email' => 'rechazo.perfil@convites.test',
            'modalidad' => 'virtual',
            'disponibilidad' => 'Tardes',
            'descripcion' => 'Descripción de prueba con suficiente longitud.',
        ])->assertCreated();

        $profesional = Profesional::query()->where('email', 'rechazo.perfil@convites.test')->firstOrFail();

        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');

        $this->actingAs($moderator, 'sanctum')
            ->postJson("/api/moderacion/profesionales/{$profesional->id}/rechazar", [
                'nota' => 'Falta información de contacto.',
            ])
            ->assertOk();

        $this->assertFalse($user->fresh()->hasRole('profesional'));
    }

    public function test_puede_ver_su_propio_perfil_profesional(): void
    {
        $user = User::factory()->create();
        $user->assignRole('profesional');
        $profesional = $this->crearProfesionalPropio($user);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/mi-perfil-profesional')
            ->assertOk()
            ->assertJsonPath('data.id', $profesional->id);
    }

    public function test_sin_perfil_profesional_da_404(): void
    {
        $user = User::factory()->create();
        $user->assignRole('profesional');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/mi-perfil-profesional')
            ->assertNotFound();
    }

    public function test_puede_actualizar_disponibilidad_y_descripcion_propias(): void
    {
        $user = User::factory()->create();
        $user->assignRole('profesional');
        $profesional = $this->crearProfesionalPropio($user);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/mi-perfil-profesional', [
                'disponibilidad' => 'Fines de semana',
                'descripcion' => 'Descripción actualizada con suficiente longitud.',
                'modalidad' => 'presencial',
            ])
            ->assertOk()
            ->assertJsonPath('data.disponibilidad', 'Fines de semana');

        $this->assertSame('Fines de semana', $profesional->fresh()->disponibilidad);
    }

    public function test_no_puede_actualizar_estado_desde_mi_perfil(): void
    {
        $user = User::factory()->create();
        $user->assignRole('profesional');
        $profesional = $this->crearProfesionalPropio($user);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/mi-perfil-profesional', [
                'disponibilidad' => 'Fines de semana',
                'descripcion' => 'Descripción actualizada con suficiente longitud.',
                'modalidad' => 'presencial',
                'estado' => 'aprobado',
            ]);

        $this->assertSame(EstadoProfesional::Aprobado, $profesional->fresh()->estado);
    }

    public function test_lista_sus_propias_solicitudes_recibidas(): void
    {
        $user = User::factory()->create();
        $user->assignRole('profesional');
        $profesional = $this->crearProfesionalPropio($user);

        ProfesionalSolicitud::query()->create([
            'profesional_id' => $profesional->id,
            'nombre' => 'Interesado',
            'celular' => '+57 300 000 0000',
            'preferencia_contacto' => PreferenciaContacto::Whatsapp,
            'mensaje' => 'Necesito ayuda.',
            'estado' => EstadoSolicitudProfesional::Pendiente,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/mi-perfil-profesional/solicitudes')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_demo_aportante1_vinculado_a_laura_cardona_tiene_rol_profesional(): void
    {
        // P32: DemoDataSeeder debe dejar el rol asignado sin pasos manuales.
        $this->seed();

        $user = User::query()->where('email', 'aportante1@convites.test')->firstOrFail();

        $this->assertTrue($user->hasRole('profesional'));
        $this->assertTrue($user->hasRole('member'));

        $token = $this->postJson('/api/auth/login', [
            'email' => 'aportante1@convites.test',
            'password' => 'password',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/api/mi-perfil-profesional')
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Laura Cardona');
    }

    public function test_otro_profesional_no_ve_solicitudes_ajenas(): void
    {
        $userA = User::factory()->create();
        $userA->assignRole('profesional');
        $profesionalA = $this->crearProfesionalPropio($userA);

        $userB = User::factory()->create();
        $userB->assignRole('profesional');
        $this->crearProfesionalPropio($userB);

        ProfesionalSolicitud::query()->create([
            'profesional_id' => $profesionalA->id,
            'nombre' => 'Interesado',
            'celular' => '+57 300 000 0000',
            'preferencia_contacto' => PreferenciaContacto::Whatsapp,
            'mensaje' => 'Necesito ayuda.',
            'estado' => EstadoSolicitudProfesional::Pendiente,
        ]);

        $this->actingAs($userB, 'sanctum')
            ->getJson('/api/mi-perfil-profesional/solicitudes')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
