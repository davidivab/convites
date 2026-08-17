<?php

namespace Tests\Feature;

use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use App\Models\IniciativaPuntoAcopio;
use App\Models\Municipio;
use App\Models\User;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AportePuntoAcopioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
    }

    private function crearIniciativaConPunto(): array
    {
        $municipios = Municipio::query()->where('activo', true)->take(2)->pluck('id');
        $creador = User::factory()->create();
        $creador->assignRole('member');

        $iniciativa = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipios[0],
            'zona_id' => null,
        ]);

        $punto = IniciativaPuntoAcopio::query()->create([
            'iniciativa_id' => $iniciativa->id,
            'municipio_id' => $municipios[1],
            'nombre' => 'Punto Bogotá',
            'direccion' => 'Calle 1 # 2-3',
            'orden' => 1,
        ]);

        return [$iniciativa, $punto];
    }

    public function test_aporta_con_punto_de_acopio_valido(): void
    {
        [$iniciativa, $punto] = $this->crearIniciativaConPunto();

        $aportante = User::factory()->create();
        $aportante->assignRole('member');

        $this->actingAs($aportante, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", [
                'asiste_al_convite' => true,
                'punto_acopio_id' => $punto->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.punto_acopio.id', $punto->id)
            ->assertJsonPath('data.punto_acopio.nombre', 'Punto Bogotá');

        $this->assertSame($punto->id, $iniciativa->aportes()->firstOrFail()->punto_acopio_id);
    }

    public function test_rechaza_punto_de_acopio_de_otra_iniciativa(): void
    {
        [$iniciativa] = $this->crearIniciativaConPunto();
        [, $puntoAjeno] = $this->crearIniciativaConPunto();

        $aportante = User::factory()->create();
        $aportante->assignRole('member');

        $this->actingAs($aportante, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", [
                'asiste_al_convite' => true,
                'punto_acopio_id' => $puntoAjeno->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['punto_acopio_id']);
    }

    public function test_aporte_sin_punto_de_acopio_sigue_funcionando(): void
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $creador = User::factory()->create();
        $creador->assignRole('member');
        $iniciativa = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
        ]);

        $aportante = User::factory()->create();
        $aportante->assignRole('member');

        $this->actingAs($aportante, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", [
                'asiste_al_convite' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.punto_acopio', null);
    }
}
