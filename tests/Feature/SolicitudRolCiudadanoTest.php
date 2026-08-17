<?php

namespace Tests\Feature;

use App\Models\Municipio;
use App\Models\User;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolicitudRolCiudadanoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
    }

    public function test_ciudadano_solicita_rol_moderador_con_municipios(): void
    {
        $user = User::factory()->create();
        $user->assignRole('member');
        $municipios = Municipio::query()->where('activo', true)->take(2)->pluck('id');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/solicitudes-rol', [
                'rol' => 'moderador',
                'municipio_ids' => $municipios->all(),
                'mensaje' => 'Quiero ayudar a moderar mi municipio.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.rol', 'moderador')
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonCount(2, 'data.municipios');
    }

    public function test_ciudadano_puede_tener_solicitudes_de_moderador_y_voluntario_en_paralelo(): void
    {
        $user = User::factory()->create();
        $user->assignRole('member');
        $municipioId = Municipio::query()->where('activo', true)->value('id');

        $this->actingAs($user, 'sanctum')->postJson('/api/solicitudes-rol', [
            'rol' => 'moderador',
            'municipio_ids' => [$municipioId],
        ])->assertCreated();

        $this->actingAs($user, 'sanctum')->postJson('/api/solicitudes-rol', [
            'rol' => 'voluntario',
            'municipio_ids' => [$municipioId],
        ])->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/mis-solicitudes-rol')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_rechaza_segunda_solicitud_pendiente_del_mismo_rol(): void
    {
        $user = User::factory()->create();
        $user->assignRole('member');
        $municipioId = Municipio::query()->where('activo', true)->value('id');

        $this->actingAs($user, 'sanctum')->postJson('/api/solicitudes-rol', [
            'rol' => 'moderador',
            'municipio_ids' => [$municipioId],
        ])->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/solicitudes-rol', [
                'rol' => 'moderador',
                'municipio_ids' => [$municipioId],
            ])
            ->assertUnprocessable();
    }

    public function test_rechaza_solicitud_de_un_rol_que_ya_tiene(): void
    {
        $user = User::factory()->create();
        $user->assignRole('member');
        $user->assignRole('moderator');
        $municipioId = Municipio::query()->where('activo', true)->value('id');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/solicitudes-rol', [
                'rol' => 'moderador',
                'municipio_ids' => [$municipioId],
            ])
            ->assertUnprocessable();
    }

    public function test_requiere_al_menos_un_municipio(): void
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/solicitudes-rol', ['rol' => 'voluntario', 'municipio_ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['municipio_ids']);
    }

    public function test_mis_solicitudes_no_muestra_las_de_otro_usuario(): void
    {
        $userA = User::factory()->create();
        $userA->assignRole('member');
        $municipioId = Municipio::query()->where('activo', true)->value('id');
        $this->actingAs($userA, 'sanctum')->postJson('/api/solicitudes-rol', [
            'rol' => 'moderador',
            'municipio_ids' => [$municipioId],
        ])->assertCreated();

        $userB = User::factory()->create();
        $userB->assignRole('member');

        $this->actingAs($userB, 'sanctum')
            ->getJson('/api/mis-solicitudes-rol')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
