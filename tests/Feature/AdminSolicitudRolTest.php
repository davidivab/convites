<?php

namespace Tests\Feature;

use App\Models\Municipio;
use App\Models\SolicitudRol;
use App\Models\User;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSolicitudRolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
    }

    private function crearSolicitudPendiente(string $rol = 'moderador'): array
    {
        $ciudadano = User::factory()->create();
        $ciudadano->assignRole('member');
        $municipios = Municipio::query()->where('activo', true)->take(2)->pluck('id');

        $solicitud = SolicitudRol::query()->create([
            'user_id' => $ciudadano->id,
            'rol' => $rol,
            'estado' => 'pendiente',
        ]);
        $solicitud->municipios()->sync($municipios);

        return [$ciudadano, $solicitud, $municipios->all()];
    }

    public function test_admin_lista_solicitudes_pendientes(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->crearSolicitudPendiente('moderador');
        $this->crearSolicitudPendiente('voluntario');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/solicitudes-rol')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/solicitudes-rol?rol=moderador')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_aprueba_solicitud_y_asigna_rol_y_municipios(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        [$ciudadano, $solicitud, $municipioIds] = $this->crearSolicitudPendiente('moderador');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/solicitudes-rol/{$solicitud->id}/aprobar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'aprobada');

        $fresh = $ciudadano->fresh();
        $this->assertTrue($fresh->hasRole('moderator'));
        $this->assertTrue($fresh->hasRole('member'), 'no debe perder el rol member al aprobar');
        $this->assertEqualsCanonicalizing($municipioIds, $fresh->assignedMunicipioIds());
    }

    public function test_admin_rechaza_solicitud_con_nota(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        [$ciudadano, $solicitud] = $this->crearSolicitudPendiente('voluntario');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/solicitudes-rol/{$solicitud->id}/rechazar", [
                'nota_revision' => 'Faltan datos de contacto.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'rechazada')
            ->assertJsonPath('data.nota_revision', 'Faltan datos de contacto.');

        $this->assertFalse($ciudadano->fresh()->hasRole('voluntario'));
    }

    public function test_rechazar_sin_nota_falla(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        [, $solicitud] = $this->crearSolicitudPendiente('voluntario');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/solicitudes-rol/{$solicitud->id}/rechazar")
            ->assertUnprocessable();
    }

    public function test_ciudadano_no_puede_entrar_a_los_endpoints_admin(): void
    {
        $ciudadano = User::factory()->create();
        $ciudadano->assignRole('member');
        [, $solicitud] = $this->crearSolicitudPendiente('moderador');

        $this->actingAs($ciudadano, 'sanctum')
            ->getJson('/api/admin/solicitudes-rol')
            ->assertForbidden();

        $this->actingAs($ciudadano, 'sanctum')
            ->postJson("/api/admin/solicitudes-rol/{$solicitud->id}/aprobar")
            ->assertForbidden();
    }
}
