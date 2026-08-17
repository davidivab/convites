<?php

namespace Tests\Feature;

use App\Enums\EstadoIniciativa;
use App\Models\Iniciativa;
use App\Models\Municipio;
use App\Models\User;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IniciativaCerrarPropiaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
    }

    private function crearIniciativaPropia(User $creador, EstadoIniciativa $estado = EstadoIniciativa::EnCurso): Iniciativa
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();

        return Iniciativa::factory()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
            'estado' => $estado,
        ]);
    }

    public function test_creador_cierra_su_propio_convite(): void
    {
        $creador = User::factory()->create();
        $creador->assignRole('member');
        $iniciativa = $this->crearIniciativaPropia($creador);

        $this->actingAs($creador, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/cerrar", ['nota' => 'Ya se completó el convite.'])
            ->assertOk()
            ->assertJsonPath('data.estado', 'cerrada');

        $this->assertSame(EstadoIniciativa::Cerrada, $iniciativa->fresh()->estado);
        $this->assertDatabaseHas('moderacion_acciones', [
            'iniciativa_id' => $iniciativa->id,
            'user_id' => $creador->id,
        ]);
    }

    public function test_otro_usuario_no_puede_cerrar_convite_ajeno(): void
    {
        $creador = User::factory()->create();
        $creador->assignRole('member');
        $iniciativa = $this->crearIniciativaPropia($creador);

        $otro = User::factory()->create();
        $otro->assignRole('member');

        $this->actingAs($otro, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/cerrar")
            ->assertForbidden();

        $this->assertSame(EstadoIniciativa::EnCurso, $iniciativa->fresh()->estado);
    }

    public function test_no_se_puede_cerrar_un_borrador(): void
    {
        $creador = User::factory()->create();
        $creador->assignRole('member');
        $iniciativa = $this->crearIniciativaPropia($creador, EstadoIniciativa::Borrador);

        $this->actingAs($creador, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/cerrar")
            ->assertUnprocessable();

        $this->assertSame(EstadoIniciativa::Borrador, $iniciativa->fresh()->estado);
    }

    public function test_moderador_tambien_puede_cerrar_via_endpoint_propio(): void
    {
        $creador = User::factory()->create();
        $creador->assignRole('member');
        $iniciativa = $this->crearIniciativaPropia($creador);

        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        $moderator->municipiosAsignados()->sync([$iniciativa->municipio_id]);

        $this->actingAs($moderator, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/cerrar")
            ->assertOk();
    }
}
