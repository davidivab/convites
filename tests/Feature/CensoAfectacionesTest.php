<?php

namespace Tests\Feature;

use App\Enums\TipoCentro;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\CensoAfectacionesSeeder;
use Database\Seeders\ColombiaGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P45 — tipo censo + puntos oficiales Pereira + url_externa.
 */
class CensoAfectacionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogosSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
        $this->seed(CensoAfectacionesSeeder::class);
    }

    public function test_index_filtra_tipo_censo_con_portal_y_puntos(): void
    {
        $res = $this->getJson('/api/centros?tipo=censo');
        $res->assertOk();

        $data = collect($res->json('data'));
        // 1 portal + 24 puntos presenciales
        $this->assertCount(25, $data);
        $this->assertTrue($data->every(fn ($row) => ($row['tipo'] ?? null) === TipoCentro::Censo->value));
        $this->assertTrue($data->every(
            fn ($row) => ($row['url_externa'] ?? null) === 'https://sospereira.com/',
        ));

        $nombres = $data->pluck('nombre');
        $this->assertTrue($nombres->contains('Portal SOS Pereira — censo de afectaciones'));
        $this->assertTrue($nombres->contains('Comuna Centro — Parque El Lago'));
        $this->assertTrue($nombres->contains('Combia Baja — Mall de Combia'));
        $this->assertTrue($nombres->contains('Corregimiento Estrella La Palmilla — Corregiduría de La Estrella'));
    }

    public function test_tipo_censo_label_en_recurso(): void
    {
        $res = $this->getJson('/api/centros?tipo=censo');
        $res->assertOk();
        $first = $res->json('data.0');
        $this->assertSame('Censo de afectaciones', $first['tipo_label'] ?? null);
    }
}
