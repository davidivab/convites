<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Iniciativa;
use App\Models\IniciativaAvance;
use App\Models\IniciativaAvanceMedia;
use App\Models\IniciativaItem;
use App\Models\IniciativaProveedor;
use App\Models\IniciativaPuntoAcopio;
use App\Models\Municipio;
use App\Models\User;
use App\Support\UniqueSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Soft deletes en puntos de acopio, proveedores, ítems y avances:
 * el listado los omite; la fila queda con deleted_at para restaurar en BD.
 */
class IniciativaRecursosSoftDeletesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function autor(): User
    {
        return User::query()->where('email', 'member@convites.test')->firstOrFail();
    }

    private function crearIniciativaDe(User $autor): Iniciativa
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();

        return Iniciativa::factory()->create([
            'user_id' => $autor->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
        ]);
    }

    public function test_tablas_tienen_columna_deleted_at(): void
    {
        foreach ([
            'iniciativa_puntos_acopio',
            'iniciativa_proveedores',
            'iniciativa_items',
            'iniciativa_avances',
        ] as $table) {
            $this->assertTrue(
                Schema::hasColumn($table, 'deleted_at'),
                "Expected softDeletes on {$table}",
            );
        }
    }

    public function test_sync_items_puntos_y_proveedores_usa_soft_delete(): void
    {
        $autor = $this->autor();
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = Categoria::query()->value('id');

        $create = $this->actingAs($autor, 'sanctum')->postJson('/api/iniciativas', [
            'municipio_id' => $municipio->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Soft delete sync',
            'resumen' => 'Prueba soft delete en sync.',
            'historia' => ['Historia mínima.'],
            'urgencia' => 'media',
            'lugar_convite' => 'Salón',
            'persona_responsable' => 'Vecino',
            'quien_respalda' => 'JAC',
            'telefono_contacto' => '+57 300 000 0000',
            'items' => [
                ['nombre' => 'Cemento', 'unidad' => 'bultos', 'cantidad_meta' => 10],
            ],
            'puntos_acopio' => [
                [
                    'municipio_id' => $municipio->id,
                    'nombre' => 'Punto A',
                    'direccion' => 'Calle 1',
                ],
            ],
            'proveedores' => [
                [
                    'nombre' => 'Ferretería',
                    'instrucciones_pago' => 'Efectivo',
                ],
            ],
        ]);

        $create->assertCreated();
        $id = (int) $create->json('data.id');
        $version = (int) $create->json('data.version');
        $itemId = (int) $create->json('data.items.0.id');
        $puntoId = (int) $create->json('data.puntos_acopio.0.id');
        $proveedorId = (int) $create->json('data.proveedores.0.id');

        $this->actingAs($autor, 'sanctum')
            ->putJson("/api/iniciativas/{$id}", [
                'municipio_id' => $municipio->id,
                'categoria_id' => $categoriaId,
                'titulo' => 'Soft delete sync',
                'resumen' => 'Prueba soft delete en sync.',
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
                'puntos_acopio' => [
                    [
                        'municipio_id' => $municipio->id,
                        'nombre' => 'Punto B',
                        'direccion' => 'Calle 2',
                    ],
                ],
                'proveedores' => [
                    [
                        'nombre' => 'Depósito nuevo',
                        'instrucciones_pago' => 'Transferencia',
                    ],
                ],
            ])
            ->assertOk();

        $this->assertSoftDeleted('iniciativa_items', ['id' => $itemId]);
        $this->assertSoftDeleted('iniciativa_puntos_acopio', ['id' => $puntoId]);
        $this->assertSoftDeleted('iniciativa_proveedores', ['id' => $proveedorId]);

        $iniciativa = Iniciativa::query()->findOrFail($id);
        $this->assertSame(1, $iniciativa->items()->count());
        $this->assertSame(1, $iniciativa->puntosAcopio()->count());
        $this->assertSame(1, $iniciativa->proveedores()->count());

        $this->assertSame(2, IniciativaItem::withTrashed()->where('iniciativa_id', $id)->count());
        $this->assertSame(2, IniciativaPuntoAcopio::withTrashed()->where('iniciativa_id', $id)->count());
        $this->assertSame(2, IniciativaProveedor::withTrashed()->where('iniciativa_id', $id)->count());
    }

    public function test_eliminar_avance_es_soft_delete_y_conserva_media(): void
    {
        $autor = $this->autor();
        $iniciativa = $this->crearIniciativaDe($autor);

        $avance = IniciativaAvance::factory()->create([
            'iniciativa_id' => $iniciativa->id,
            'user_id' => $autor->id,
            'slug' => 'reporte-inicial',
        ]);

        $media = IniciativaAvanceMedia::query()->create([
            'iniciativa_avance_id' => $avance->id,
            'path' => 'avances/test.jpg',
            'tipo' => 'imagen',
            'orden' => 0,
        ]);

        $this->actingAs($autor, 'sanctum')
            ->deleteJson("/api/iniciativas/{$iniciativa->uuid}/avances/{$avance->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('iniciativa_avances', ['id' => $avance->id]);
        $this->assertDatabaseHas('iniciativa_avance_media', ['id' => $media->id]);
        $this->assertSame(0, $iniciativa->fresh()->avances()->count());
        $this->assertSame(1, IniciativaAvance::withTrashed()->where('iniciativa_id', $iniciativa->id)->count());
    }

    public function test_slug_de_avance_respeta_soft_deleted_al_regenerar(): void
    {
        $autor = $this->autor();
        $iniciativa = $this->crearIniciativaDe($autor);

        $avance = IniciativaAvance::factory()->create([
            'iniciativa_id' => $iniciativa->id,
            'user_id' => $autor->id,
            'titulo' => 'Reporte general',
            'slug' => 'reporte-general',
        ]);
        $avance->delete();

        $slug = UniqueSlug::forAvance($iniciativa->id, 'Reporte general');

        $this->assertSame('reporte-general-1', $slug);
    }
}
