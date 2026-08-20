<?php

namespace Tests\Feature;

use App\Enums\EstadoIniciativa;
use App\Enums\Urgencia;
use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use App\Models\Municipio;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "¿Tengo este material, quién lo necesita?" — búsqueda inversa por ítem
 * (sugerencia de Patricia): en vez de listar convites, lista los ítems que
 * les faltan a los convites publicados, para que un donante con una oferta
 * concreta encuentre a quién le sirve.
 */
class MaterialesTest extends TestCase
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

    public function test_lista_items_pendientes_de_iniciativas_publicadas(): void
    {
        $municipio = $this->municipioActivo();
        $iniciativa = Iniciativa::factory()->publicada()->create(['municipio_id' => $municipio->id]);

        IniciativaItem::query()->create([
            'iniciativa_id' => $iniciativa->id,
            'nombre' => 'Tejas de zinc',
            'unidad' => 'unidades',
            'cantidad_meta' => 20,
            'cantidad_aportada' => 5,
            'orden' => 1,
        ]);

        $this->getJson('/api/materiales')
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'Tejas de zinc', 'faltante' => 15])
            ->assertJsonPath('data.0.iniciativa.slug', $iniciativa->slug);
    }

    public function test_no_incluye_items_ya_completos(): void
    {
        $municipio = $this->municipioActivo();
        $iniciativa = Iniciativa::factory()->publicada()->create(['municipio_id' => $municipio->id]);

        IniciativaItem::query()->create([
            'iniciativa_id' => $iniciativa->id,
            'nombre' => 'Cemento',
            'unidad' => 'bultos',
            'cantidad_meta' => 10,
            'cantidad_aportada' => 10,
            'orden' => 1,
        ]);

        $this->getJson('/api/materiales')->assertJsonMissing(['nombre' => 'Cemento']);
    }

    public function test_no_incluye_items_de_iniciativas_no_publicadas(): void
    {
        $municipio = $this->municipioActivo();
        $borrador = Iniciativa::factory()->create([
            'municipio_id' => $municipio->id,
            'estado' => EstadoIniciativa::EnRevision,
        ]);

        IniciativaItem::query()->create([
            'iniciativa_id' => $borrador->id,
            'nombre' => 'Colchonetas',
            'unidad' => 'unidades',
            'cantidad_meta' => 5,
            'cantidad_aportada' => 0,
            'orden' => 1,
        ]);

        $this->getJson('/api/materiales')->assertJsonMissing(['nombre' => 'Colchonetas']);
    }

    public function test_filtra_por_municipio(): void
    {
        $municipios = Municipio::query()->where('activo', true)->take(2)->get();
        $this->assertCount(2, $municipios, 'La suite espera al menos 2 municipios activos en el catálogo demo.');

        $iniciativaA = Iniciativa::factory()->publicada()->create(['municipio_id' => $municipios[0]->id]);
        $iniciativaB = Iniciativa::factory()->publicada()->create(['municipio_id' => $municipios[1]->id]);

        IniciativaItem::query()->create([
            'iniciativa_id' => $iniciativaA->id,
            'nombre' => 'Agua embotellada',
            'unidad' => 'litros',
            'cantidad_meta' => 100,
            'cantidad_aportada' => 0,
            'orden' => 1,
        ]);
        IniciativaItem::query()->create([
            'iniciativa_id' => $iniciativaB->id,
            'nombre' => 'Kits de aseo',
            'unidad' => 'unidades',
            'cantidad_meta' => 30,
            'cantidad_aportada' => 0,
            'orden' => 1,
        ]);

        $this->getJson('/api/materiales?municipio='.$municipios[0]->slug)
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'Agua embotellada'])
            ->assertJsonMissing(['nombre' => 'Kits de aseo']);
    }

    public function test_filtra_por_nombre_de_material_con_q(): void
    {
        $municipio = $this->municipioActivo();
        $iniciativa = Iniciativa::factory()->publicada()->create(['municipio_id' => $municipio->id]);

        IniciativaItem::query()->create([
            'iniciativa_id' => $iniciativa->id,
            'nombre' => 'Tejas de zinc',
            'unidad' => 'unidades',
            'cantidad_meta' => 20,
            'cantidad_aportada' => 0,
            'orden' => 1,
        ]);
        IniciativaItem::query()->create([
            'iniciativa_id' => $iniciativa->id,
            'nombre' => 'Ladrillos',
            'unidad' => 'unidades',
            'cantidad_meta' => 200,
            'cantidad_aportada' => 0,
            'orden' => 2,
        ]);

        $this->getJson('/api/materiales?q=teja')
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'Tejas de zinc'])
            ->assertJsonMissing(['nombre' => 'Ladrillos']);
    }

    public function test_incluye_descripcion_y_valor_aproximado_cuando_estan_definidos(): void
    {
        $municipio = $this->municipioActivo();
        $iniciativa = Iniciativa::factory()->publicada()->create(['municipio_id' => $municipio->id]);

        IniciativaItem::query()->create([
            'iniciativa_id' => $iniciativa->id,
            'nombre' => 'Tejas de zinc',
            'unidad' => 'unidades',
            'cantidad_meta' => 20,
            'cantidad_aportada' => 5,
            'descripcion' => 'Se consiguen en el depósito de materiales del barrio.',
            'valor_unitario_aprox' => 15000,
            'orden' => 1,
        ]);

        $this->getJson('/api/materiales')
            ->assertOk()
            ->assertJsonFragment([
                'nombre' => 'Tejas de zinc',
                'descripcion' => 'Se consiguen en el depósito de materiales del barrio.',
                'valor_unitario_aprox' => 15000,
                'valor_meta_aprox' => 300000.0,
                'valor_aportado_aprox' => 75000.0,
            ]);
    }

    public function test_descripcion_y_valor_aproximado_son_null_cuando_no_se_definen(): void
    {
        $municipio = $this->municipioActivo();
        $iniciativa = Iniciativa::factory()->publicada()->create(['municipio_id' => $municipio->id]);

        IniciativaItem::query()->create([
            'iniciativa_id' => $iniciativa->id,
            'nombre' => 'Ladrillos',
            'unidad' => 'unidades',
            'cantidad_meta' => 200,
            'cantidad_aportada' => 0,
            'orden' => 1,
        ]);

        $this->getJson('/api/materiales')
            ->assertOk()
            ->assertJsonPath('data.0.descripcion', null)
            ->assertJsonPath('data.0.valor_unitario_aprox', null)
            ->assertJsonPath('data.0.valor_meta_aprox', null)
            ->assertJsonPath('data.0.valor_aportado_aprox', null);
    }

    public function test_filtra_por_urgencia_y_categoria_igual_que_explorar(): void
    {
        $municipio = $this->municipioActivo();
        $urgente = Iniciativa::factory()->publicada()->create([
            'municipio_id' => $municipio->id,
            'urgencia' => Urgencia::Alta,
        ]);
        $noUrgente = Iniciativa::factory()->publicada()->create([
            'municipio_id' => $municipio->id,
            'urgencia' => Urgencia::Baja,
        ]);

        IniciativaItem::query()->create([
            'iniciativa_id' => $urgente->id,
            'nombre' => 'Botiquín',
            'unidad' => 'unidades',
            'cantidad_meta' => 3,
            'cantidad_aportada' => 0,
            'orden' => 1,
        ]);
        IniciativaItem::query()->create([
            'iniciativa_id' => $noUrgente->id,
            'nombre' => 'Juguetes',
            'unidad' => 'unidades',
            'cantidad_meta' => 15,
            'cantidad_aportada' => 0,
            'orden' => 1,
        ]);

        $this->getJson('/api/materiales?urgencia='.Urgencia::Alta->value)
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'Botiquín'])
            ->assertJsonMissing(['nombre' => 'Juguetes']);
    }
}
