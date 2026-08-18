<?php

namespace Tests\Feature;

use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use App\Models\Municipio;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P48: Explorar filtraba/ordenaba en el cliente sobre per_page=50 — el
 * server ahora soporta `orden` (fecha|avance|nombre) + `dir` (asc|desc)
 * tanto en /api/iniciativas como en /api/materiales.
 */
class ExplorarOrdenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
    }

    private function municipioActivo(): Municipio
    {
        return Municipio::query()->where('activo', true)->firstOrFail();
    }

    public function test_iniciativas_orden_por_nombre_asc(): void
    {
        $municipio = $this->municipioActivo();
        Iniciativa::factory()->publicada()->create(['municipio_id' => $municipio->id, 'titulo' => 'Zapatos para el barrio']);
        Iniciativa::factory()->publicada()->create(['municipio_id' => $municipio->id, 'titulo' => 'Ayuda para Ana']);

        $response = $this->getJson('/api/iniciativas?orden=nombre&dir=asc')->assertOk();
        $titulos = collect($response->json('data'))->pluck('titulo')->all();

        $this->assertSame(['Ayuda para Ana', 'Zapatos para el barrio'], $titulos);
    }

    public function test_iniciativas_orden_por_avance_desc(): void
    {
        $municipio = $this->municipioActivo();
        $bajo = Iniciativa::factory()->publicada()->create(['municipio_id' => $municipio->id, 'titulo' => 'Bajo avance', 'progreso_cache' => 10]);
        $alto = Iniciativa::factory()->publicada()->create(['municipio_id' => $municipio->id, 'titulo' => 'Alto avance', 'progreso_cache' => 90]);

        $response = $this->getJson('/api/iniciativas?orden=avance&dir=desc')->assertOk();
        $titulos = collect($response->json('data'))->pluck('titulo')->all();

        $this->assertSame(['Alto avance', 'Bajo avance'], $titulos);
    }

    public function test_iniciativas_orden_por_fecha(): void
    {
        $municipio = $this->municipioActivo();
        $lejos = Iniciativa::factory()->publicada()->create([
            'municipio_id' => $municipio->id,
            'titulo' => 'Convite lejano',
            'fecha_convite' => now()->addMonths(3)->toDateString(),
        ]);
        $cerca = Iniciativa::factory()->publicada()->create([
            'municipio_id' => $municipio->id,
            'titulo' => 'Convite cercano',
            'fecha_convite' => now()->addDays(2)->toDateString(),
        ]);

        $response = $this->getJson('/api/iniciativas?orden=fecha&dir=asc')->assertOk();
        $titulos = collect($response->json('data'))->pluck('titulo')->all();

        $this->assertSame(['Convite cercano', 'Convite lejano'], $titulos);
    }

    public function test_iniciativas_sin_orden_mantiene_comportamiento_por_defecto(): void
    {
        $municipio = $this->municipioActivo();
        Iniciativa::factory()->publicada()->create(['municipio_id' => $municipio->id, 'publicada_at' => now()->subDay()]);
        $reciente = Iniciativa::factory()->publicada()->create(['municipio_id' => $municipio->id, 'publicada_at' => now()]);

        $response = $this->getJson('/api/iniciativas')->assertOk();

        $this->assertSame($reciente->id, $response->json('data.0.id'));
    }

    public function test_materiales_orden_por_avance_asc(): void
    {
        $municipio = $this->municipioActivo();
        $iniciativa = Iniciativa::factory()->publicada()->create(['municipio_id' => $municipio->id]);

        IniciativaItem::query()->create([
            'iniciativa_id' => $iniciativa->id, 'nombre' => 'Casi completo',
            'unidad' => 'unidades', 'cantidad_meta' => 10, 'cantidad_aportada' => 9, 'orden' => 1,
        ]);
        IniciativaItem::query()->create([
            'iniciativa_id' => $iniciativa->id, 'nombre' => 'Recien empezado',
            'unidad' => 'unidades', 'cantidad_meta' => 10, 'cantidad_aportada' => 1, 'orden' => 2,
        ]);

        $response = $this->getJson('/api/materiales?orden=avance&dir=asc')->assertOk();
        $nombres = collect($response->json('data'))->pluck('nombre')->all();

        $this->assertSame(['Recien empezado', 'Casi completo'], $nombres);
    }

    public function test_materiales_orden_por_fecha_del_convite(): void
    {
        $municipio = $this->municipioActivo();
        $lejos = Iniciativa::factory()->publicada()->create([
            'municipio_id' => $municipio->id,
            'fecha_convite' => now()->addMonths(3)->toDateString(),
        ]);
        $cerca = Iniciativa::factory()->publicada()->create([
            'municipio_id' => $municipio->id,
            'fecha_convite' => now()->addDays(2)->toDateString(),
        ]);

        IniciativaItem::query()->create([
            'iniciativa_id' => $lejos->id, 'nombre' => 'Item lejano',
            'unidad' => 'unidades', 'cantidad_meta' => 5, 'cantidad_aportada' => 0, 'orden' => 1,
        ]);
        IniciativaItem::query()->create([
            'iniciativa_id' => $cerca->id, 'nombre' => 'Item cercano',
            'unidad' => 'unidades', 'cantidad_meta' => 5, 'cantidad_aportada' => 0, 'orden' => 1,
        ]);

        $response = $this->getJson('/api/materiales?orden=fecha&dir=asc')->assertOk();
        $nombres = collect($response->json('data'))->pluck('nombre')->all();

        $this->assertSame(['Item cercano', 'Item lejano'], $nombres);
    }

    public function test_materiales_sin_orden_mantiene_orden_alfabetico_por_nombre(): void
    {
        $municipio = $this->municipioActivo();
        $iniciativa = Iniciativa::factory()->publicada()->create(['municipio_id' => $municipio->id]);

        IniciativaItem::query()->create([
            'iniciativa_id' => $iniciativa->id, 'nombre' => 'Zapatos',
            'unidad' => 'unidades', 'cantidad_meta' => 5, 'cantidad_aportada' => 0, 'orden' => 1,
        ]);
        IniciativaItem::query()->create([
            'iniciativa_id' => $iniciativa->id, 'nombre' => 'Agua',
            'unidad' => 'unidades', 'cantidad_meta' => 5, 'cantidad_aportada' => 0, 'orden' => 2,
        ]);

        $response = $this->getJson('/api/materiales')->assertOk();
        $nombres = collect($response->json('data'))->pluck('nombre')->all();

        $this->assertSame(['Agua', 'Zapatos'], $nombres);
    }
}
