<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\Municipio;
use Database\Seeders\ColombiaGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogoGeoEmergenciaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ColombiaGeoSeeder::class);
    }

    public function test_departamentos_emergencia_salen_primero(): void
    {
        $response = $this->getJson('/api/catalogos/departamentos?incluir_inactivos=1');

        $response->assertOk();
        $nombres = collect($response->json('data'))->pluck('nombre')->take(3)->all();

        $this->assertSame(['Chocó', 'Risaralda', 'Valle del Cauca'], $nombres);
        $this->assertTrue((bool) $response->json('data.0.emergencia'));
    }

    public function test_municipios_de_emergencia_tienen_flag(): void
    {
        $risaralda = Departamento::query()->where('nombre', 'Risaralda')->firstOrFail();
        $this->assertTrue($risaralda->emergencia);

        $mun = Municipio::query()->where('departamento_id', $risaralda->id)->firstOrFail();
        $this->assertTrue($mun->emergencia);

        $response = $this->getJson(
            '/api/catalogos/municipios?departamento_id='.$risaralda->id.'&incluir_inactivos=1',
        );
        $response->assertOk();
        $this->assertTrue((bool) $response->json('data.0.emergencia'));
    }
}
