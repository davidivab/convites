<?php

namespace Tests\Feature;

use App\Enums\EstadoAporte;
use App\Models\Aporte;
use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use App\Models\Municipio;
use App\Models\User;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Anular aporte desde organizador/admin (compromiso falso o error),
 * con motivo opcional; también aporta el dueño del aporte.
 */
class AporteAnularTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
    }

    /**
     * @return array{0: User, 1: User, 2: Iniciativa, 3: Aporte}
     */
    private function escenario(): array
    {
        $creador = User::factory()->create();
        $creador->assignRole('member');
        $aportante = User::factory()->create();
        $aportante->assignRole('member');

        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $iniciativa = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
            'progreso_cache' => 0,
        ]);
        $item = IniciativaItem::query()->create([
            'iniciativa_id' => $iniciativa->id,
            'nombre' => 'Cemento',
            'unidad' => 'bultos',
            'cantidad_meta' => 10,
            'cantidad_aportada' => 0,
            'orden' => 1,
        ]);

        $this->actingAs($aportante, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", [
                'items' => [['iniciativa_item_id' => $item->id, 'cantidad' => 2]],
            ])
            ->assertCreated();

        $aporte = Aporte::query()->where('iniciativa_id', $iniciativa->id)->firstOrFail();

        return [$creador, $aportante, $iniciativa->fresh(), $aporte->fresh()];
    }

    public function test_creador_puede_anular_aporte_ajeno_con_motivo(): void
    {
        [$creador, , $iniciativa, $aporte] = $this->escenario();

        $this->assertSame(EstadoAporte::Confirmado, $aporte->estado);
        $this->assertGreaterThan(0, (int) $iniciativa->fresh()->progreso_cache);

        $this->actingAs($creador, 'sanctum')
            ->postJson("/api/aportes/{$aporte->id}/cancelar", [
                'motivo' => 'No entregó lo prometido; era falso.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelado')
            ->assertJsonPath('data.cancelado_motivo', 'No entregó lo prometido; era falso.');

        $fresh = $aporte->fresh();
        $this->assertSame(EstadoAporte::Cancelado, $fresh->estado);
        $this->assertSame($creador->id, $fresh->cancelado_por_user_id);
        $this->assertNotNull($fresh->cancelado_at);
        $this->assertSame(0, (int) $iniciativa->fresh()->progreso_cache);
    }

    public function test_creador_puede_anular_sin_motivo(): void
    {
        [$creador, , , $aporte] = $this->escenario();

        $this->actingAs($creador, 'sanctum')
            ->postJson("/api/aportes/{$aporte->id}/cancelar", [])
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelado')
            ->assertJsonPath('data.cancelado_motivo', null);
    }

    public function test_aportante_sigue_pudiendo_cancelar_el_suyo(): void
    {
        [, $aportante, , $aporte] = $this->escenario();

        $this->actingAs($aportante, 'sanctum')
            ->postJson("/api/aportes/{$aporte->id}/cancelar", [
                'motivo' => 'Ya no puedo llevarlo.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelado')
            ->assertJsonPath('data.cancelado_motivo', 'Ya no puedo llevarlo.');

        $this->assertSame($aportante->id, $aporte->fresh()->cancelado_por_user_id);
    }

    public function test_tercero_no_puede_anular(): void
    {
        [, , , $aporte] = $this->escenario();
        $tercero = User::factory()->create();
        $tercero->assignRole('member');

        $this->actingAs($tercero, 'sanctum')
            ->postJson("/api/aportes/{$aporte->id}/cancelar", [
                'motivo' => 'intento',
            ])
            ->assertForbidden();
    }

    public function test_admin_puede_anular(): void
    {
        [, , , $aporte] = $this->escenario();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/aportes/{$aporte->id}/cancelar", [
                'motivo' => 'Revisión admin',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelado');
    }

    public function test_motivo_max_500_caracteres(): void
    {
        [$creador, , , $aporte] = $this->escenario();

        $this->actingAs($creador, 'sanctum')
            ->postJson("/api/aportes/{$aporte->id}/cancelar", [
                'motivo' => str_repeat('x', 501),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo']);
    }
}
