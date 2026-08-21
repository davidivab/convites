<?php

namespace Tests\Feature;

use App\Enums\EstadoProfesional;
use App\Enums\EstadoSolicitudRol;
use App\Enums\TipoSolicitudRol;
use App\Models\Profesional;
use App\Models\SolicitudRol;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersUnificadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_tipo_todos_incluye_ciudadanos_y_roles_status(): void
    {
        $ciudadano = User::factory()->create(['name' => 'Ana Ciudadana']);
        $ciudadano->assignRole('member');

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/users?tipo=todos')
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $ciudadano->id);
        $this->assertNotNull($row);
        $this->assertSame('active', $row['roles_status']['ciudadano']);
        $this->assertSame('none', $row['roles_status']['moderador']);
        $this->assertSame('none', $row['roles_status']['voluntario']);
        $this->assertSame('none', $row['roles_status']['profesional']);
    }

    public function test_tipo_pendientes_incluye_solicitud_y_profesional(): void
    {
        $modPendiente = User::factory()->create(['name' => 'Mod Pendiente']);
        $modPendiente->assignRole('member');
        SolicitudRol::query()->create([
            'user_id' => $modPendiente->id,
            'rol' => TipoSolicitudRol::Moderador,
            'estado' => EstadoSolicitudRol::Pendiente,
            'mensaje' => 'Quiero moderar',
        ]);

        $proUser = User::factory()->create(['name' => 'Pro Pendiente']);
        $proUser->assignRole('member');
        Profesional::factory()->create([
            'user_id' => $proUser->id,
            'estado' => EstadoProfesional::Pendiente,
        ]);

        $sinPendiente = User::factory()->create(['name' => 'Sin Nada']);
        $sinPendiente->assignRole('member');

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/users?tipo=pendientes')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($modPendiente->id, $ids);
        $this->assertContains($proUser->id, $ids);
        $this->assertNotContains($sinPendiente->id, $ids);

        $modRow = collect($response->json('data'))->firstWhere('id', $modPendiente->id);
        $this->assertSame('pending', $modRow['roles_status']['moderador']);

        $proRow = collect($response->json('data'))->firstWhere('id', $proUser->id);
        $this->assertSame('pending', $proRow['roles_status']['profesional']);
    }

    public function test_show_incluye_solicitudes_y_profesional(): void
    {
        $user = User::factory()->create();
        $user->assignRole('member');
        $solicitud = SolicitudRol::query()->create([
            'user_id' => $user->id,
            'rol' => TipoSolicitudRol::Voluntario,
            'estado' => EstadoSolicitudRol::Pendiente,
            'mensaje' => 'Ayudar',
        ]);
        $pro = Profesional::factory()->create([
            'user_id' => $user->id,
            'estado' => EstadoProfesional::Pendiente,
            'titulo' => 'Psicóloga clínica',
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/users/'.$user->id)
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.solicitudes_rol.0.id', $solicitud->id)
            ->assertJsonPath('data.profesional.id', $pro->id)
            ->assertJsonPath('data.roles_status.voluntario', 'pending')
            ->assertJsonPath('data.roles_status.profesional', 'pending');
    }

    public function test_q_busca_por_celular(): void
    {
        $encontrado = User::factory()->create([
            'name' => 'Con Cel',
            'celular' => '3001112233',
        ]);
        $encontrado->assignRole('member');
        $otro = User::factory()->create(['celular' => '3119998877']);
        $otro->assignRole('member');

        $ids = collect(
            $this->actingAs($this->admin(), 'sanctum')
                ->getJson('/api/admin/users?tipo=todos&q=300111')
                ->assertOk()
                ->json('data'),
        )->pluck('id')->all();

        $this->assertSame([$encontrado->id], $ids);
    }
}
