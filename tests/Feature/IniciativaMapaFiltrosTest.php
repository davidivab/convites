<?php

namespace Tests\Feature;

use App\Models\Iniciativa;
use App\Models\Municipio;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * [P49] El index de iniciativas ya filtra por municipio/departamento
 * (slug); /api/iniciativas/mapa solo tenía zona.
 */
class IniciativaMapaFiltrosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
    }

    public function test_mapa_filtra_por_municipio(): void
    {
        $municipios = Municipio::query()->where('activo', true)->with('departamento')->take(2)->get();
        $this->assertCount(2, $municipios, 'La suite espera al menos 2 municipios activos.');

        Iniciativa::factory()->publicada()->create([
            'municipio_id' => $municipios[0]->id,
            'titulo' => 'Convite A',
            'lat' => 4.8,
            'lng' => -75.6,
            'mapa_visible' => true,
        ]);
        Iniciativa::factory()->publicada()->create([
            'municipio_id' => $municipios[1]->id,
            'titulo' => 'Convite B',
            'lat' => 4.9,
            'lng' => -75.7,
            'mapa_visible' => true,
        ]);

        $response = $this->getJson('/api/iniciativas/mapa?municipio='.$municipios[0]->slug)->assertOk();
        $titulos = collect($response->json('data'))->pluck('titulo')->all();

        $this->assertSame(['Convite A'], $titulos);
    }

    public function test_mapa_filtra_por_departamento(): void
    {
        $municipios = Municipio::query()->where('activo', true)->with('departamento')->get();
        $porDepartamento = $municipios->groupBy('departamento_id')->filter(fn ($g) => $g->count() >= 1);
        $this->assertGreaterThanOrEqual(2, $porDepartamento->count(), 'La suite espera al menos 2 departamentos activos.');

        $deptIds = $porDepartamento->keys()->take(2)->all();
        $muniA = $municipios->firstWhere('departamento_id', $deptIds[0]);
        $muniB = $municipios->firstWhere('departamento_id', $deptIds[1]);

        Iniciativa::factory()->publicada()->create([
            'municipio_id' => $muniA->id,
            'titulo' => 'Convite Depto A',
            'lat' => 4.8,
            'lng' => -75.6,
            'mapa_visible' => true,
        ]);
        Iniciativa::factory()->publicada()->create([
            'municipio_id' => $muniB->id,
            'titulo' => 'Convite Depto B',
            'lat' => 4.9,
            'lng' => -75.7,
            'mapa_visible' => true,
        ]);

        $response = $this->getJson('/api/iniciativas/mapa?departamento='.$muniA->departamento->slug)->assertOk();
        $titulos = collect($response->json('data'))->pluck('titulo')->all();

        $this->assertSame(['Convite Depto A'], $titulos);
    }
}
