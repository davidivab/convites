<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use App\Models\Municipio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * progreso_cache vs ítems: al re-sync de items el % no debe quedar stale.
 */
class IniciativaProgresoSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_show_con_items_en_cero_no_devuelve_progreso_cache_stale(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();

        $iniciativa = Iniciativa::factory()->publicada()->create([
            'user_id' => $member->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
            'progreso_cache' => 20,
        ]);

        IniciativaItem::query()->create([
            'iniciativa_id' => $iniciativa->id,
            'nombre' => 'Malla',
            'unidad' => 'metros',
            'cantidad_meta' => 10,
            'cantidad_aportada' => 0,
            'orden' => 1,
        ]);

        $this->getJson("/api/iniciativas/{$iniciativa->slug}")
            ->assertOk()
            ->assertJsonPath('data.progreso', 0)
            ->assertJsonPath('data.items.0.cantidad_aportada', 0);
    }

    public function test_update_items_recalcula_progreso_cache(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = Categoria::query()->value('id');

        $create = $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', [
            'municipio_id' => $municipio->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Progreso sync',
            'resumen' => 'Prueba de progreso tras sync de items.',
            'historia' => ['Historia mínima.'],
            'urgencia' => 'media',
            'lugar_convite' => 'Salón',
            'persona_responsable' => 'Vecino',
            'quien_respalda' => 'JAC',
            'telefono_contacto' => '+57 300 000 0000',
            'items' => [
                ['nombre' => 'Cemento', 'unidad' => 'bultos', 'cantidad_meta' => 10],
            ],
        ]);

        $create->assertCreated();
        $id = (int) $create->json('data.id');

        Iniciativa::query()->whereKey($id)->update(['progreso_cache' => 20]);
        IniciativaItem::query()->where('iniciativa_id', $id)->update(['cantidad_aportada' => 2]);

        $version = (int) Iniciativa::query()->findOrFail($id)->version;

        $this->actingAs($member, 'sanctum')
            ->putJson("/api/iniciativas/{$id}", [
                'municipio_id' => $municipio->id,
                'categoria_id' => $categoriaId,
                'titulo' => 'Progreso sync',
                'resumen' => 'Prueba de progreso tras sync de items.',
                'historia' => ['Historia mínima.'],
                'urgencia' => 'media',
                'lugar_convite' => 'Salón',
                'persona_responsable' => 'Vecino',
                'quien_respalda' => 'JAC',
                'telefono_contacto' => '+57 300 000 0000',
                'version' => $version,
                'items' => [
                    ['nombre' => 'Arena', 'unidad' => 'm3', 'cantidad_meta' => 5],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.progreso', 0);

        $this->assertSame(0, (int) Iniciativa::query()->findOrFail($id)->progreso_cache);
    }
}
