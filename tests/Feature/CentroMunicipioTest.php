<?php

namespace Tests\Feature;

use App\Enums\EstadoCentro;
use App\Enums\TipoCentro;
use App\Models\Centro;
use App\Models\Municipio;
use App\Models\Zona;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\ColombiaGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P36 — centros exponen y filtran por municipio_id.
 */
class CentroMunicipioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogosSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
    }

    public function test_index_includes_municipio_and_filters_by_municipio_id(): void
    {
        $zona = Zona::query()->firstOrFail();
        $municipios = Municipio::query()->where('activo', true)->orderBy('id')->take(2)->get();
        $this->assertGreaterThanOrEqual(2, $municipios->count());

        Centro::query()->create([
            'tipo' => TipoCentro::Acopio,
            'nombre' => 'Acopio municipio A',
            'zona_id' => $zona->id,
            'municipio_id' => $municipios[0]->id,
            'direccion' => 'Calle A',
            'estado' => EstadoCentro::Abierto,
            'descripcion' => 'Centro de prueba A',
            'activo' => true,
            'orden' => 1,
            'emergencia' => false,
        ]);
        Centro::query()->create([
            'tipo' => TipoCentro::Acopio,
            'nombre' => 'Acopio municipio B',
            'zona_id' => $zona->id,
            'municipio_id' => $municipios[1]->id,
            'direccion' => 'Calle B',
            'estado' => EstadoCentro::Abierto,
            'descripcion' => 'Centro de prueba B',
            'activo' => true,
            'orden' => 2,
            'emergencia' => false,
        ]);

        $all = $this->getJson('/api/centros');
        $all->assertOk();
        $this->assertTrue(
            collect($all->json('data'))->contains(
                fn ($row) => ($row['nombre'] ?? '') === 'Acopio municipio A'
                    && ($row['municipio']['id'] ?? null) === $municipios[0]->id,
            ),
        );

        $filtered = $this->getJson('/api/centros?municipio_id='.$municipios[0]->id);
        $filtered->assertOk();
        $nombres = collect($filtered->json('data'))->pluck('nombre');
        $this->assertTrue($nombres->contains('Acopio municipio A'));
        $this->assertFalse($nombres->contains('Acopio municipio B'));
    }
}
