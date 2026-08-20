<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\Iniciativa;
use App\Models\Municipio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P33 — puntos de acopio remotos (destino ≠ ciudades de recolección).
 */
class IniciativaPuntosAcopioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_create_iniciativa_with_remote_puntos_acopio(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $destino = Municipio::query()->where('activo', true)->orderBy('id')->firstOrFail();
        $bogota = $this->municipioByNombreLike('Bogot')
            ?? Municipio::query()->where('id', '!=', $destino->id)->orderBy('id')->firstOrFail();
        $medellin = $this->municipioByNombreLike('Medell')
            ?? Municipio::query()
                ->whereNotIn('id', [$destino->id, $bogota->id])
                ->orderBy('id')
                ->firstOrFail();
        $categoriaId = \App\Models\Categoria::query()->value('id');

        $create = $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', [
            'municipio_id' => $destino->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Ayuda Chocó con acopio remoto',
            'resumen' => 'Convite destino Chocó con recolección en otras ciudades.',
            'historia' => ['La comunidad necesita insumos; se recibe en Bogotá y Medellín.'],
            'urgencia' => 'alta',
            'lugar_convite' => 'Plaza central destino',
            'persona_responsable' => 'Organizador Demo',
            'quien_respalda' => 'JAC Demo',
            'telefono_contacto' => '+57 300 111 2233',
            'items' => [
                ['nombre' => 'Mercado', 'unidad' => 'kits', 'cantidad_meta' => 50],
            ],
            'puntos_acopio' => [
                [
                    'municipio_id' => $bogota->id,
                    'nombre' => 'Acopio Bogotá Norte',
                    'direccion' => 'Calle 100 #15-20',
                    'horario' => 'Lun–Vie 9–17',
                    'contacto' => '+57 310 000 0001',
                ],
                [
                    'municipio_id' => $medellin->id,
                    'nombre' => 'Acopio Medellín Centro',
                    'direccion' => 'Carrera 50 #40-10',
                    'horario' => 'Sáb 8–12',
                ],
            ],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.titulo', 'Ayuda Chocó con acopio remoto')
            ->assertJsonCount(2, 'data.puntos_acopio')
            ->assertJsonPath('data.puntos_acopio.0.nombre', 'Acopio Bogotá Norte')
            ->assertJsonPath('data.puntos_acopio.0.municipio.id', $bogota->id)
            ->assertJsonPath('data.puntos_acopio.1.municipio.id', $medellin->id);

        $id = $create->json('data.id');
        $this->assertSame(2, Iniciativa::query()->findOrFail($id)->puntosAcopio()->count());
    }

    public function test_update_sync_replaces_puntos_acopio(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $destino = Municipio::query()->where('activo', true)->firstOrFail();
        $otros = Municipio::query()->where('id', '!=', $destino->id)->orderBy('id')->take(2)->get();
        $this->assertGreaterThanOrEqual(2, $otros->count());
        $categoriaId = \App\Models\Categoria::query()->value('id');

        $create = $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', [
            'municipio_id' => $destino->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Sync puntos acopio',
            'resumen' => 'Prueba de reemplazo de puntos de acopio en update.',
            'historia' => ['Historia mínima.'],
            'urgencia' => 'media',
            'lugar_convite' => 'Salón',
            'persona_responsable' => 'Vecino',
            'quien_respalda' => 'JAC',
            'telefono_contacto' => '+57 300 000 0000',
            'items' => [
                ['nombre' => 'Arena', 'unidad' => 'bultos', 'cantidad_meta' => 5],
            ],
            'puntos_acopio' => [
                [
                    'municipio_id' => $otros[0]->id,
                    'nombre' => 'Punto A',
                    'direccion' => 'Dir A',
                ],
            ],
        ]);

        $create->assertCreated()->assertJsonCount(1, 'data.puntos_acopio');
        $id = $create->json('data.id');
        $version = $create->json('data.version');

        $this->actingAs($member, 'sanctum')
            ->putJson("/api/iniciativas/{$id}", [
                'municipio_id' => $destino->id,
                'categoria_id' => $categoriaId,
                'titulo' => 'Sync puntos acopio',
                'resumen' => 'Prueba de reemplazo de puntos de acopio en update.',
                'historia' => ['Historia mínima.'],
                'urgencia' => 'media',
                'lugar_convite' => 'Salón',
                'persona_responsable' => 'Vecino',
                'quien_respalda' => 'JAC',
                'telefono_contacto' => '+57 300 000 0000',
                'version' => $version,
                'items' => [
                    ['nombre' => 'Arena', 'unidad' => 'bultos', 'cantidad_meta' => 5],
                ],
                'puntos_acopio' => [
                    [
                        'municipio_id' => $otros[1]->id,
                        'nombre' => 'Punto B',
                        'direccion' => 'Dir B',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.puntos_acopio')
            ->assertJsonPath('data.puntos_acopio.0.nombre', 'Punto B')
            ->assertJsonPath('data.puntos_acopio.0.municipio.id', $otros[1]->id);
    }

    public function test_create_without_puntos_acopio_still_works(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = \App\Models\Categoria::query()->value('id');

        $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', [
            'municipio_id' => $municipio->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Sin puntos de acopio',
            'resumen' => 'Compatibilidad: create sin puntos_acopio sigue válido.',
            'historia' => ['Historia mínima.'],
            'urgencia' => 'baja',
            'lugar_convite' => 'Salón',
            'persona_responsable' => 'Vecino',
            'quien_respalda' => 'JAC',
            'telefono_contacto' => '+57 300 000 0000',
            'items' => [
                ['nombre' => 'Clavos', 'unidad' => 'cajas', 'cantidad_meta' => 3],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.puntos_acopio', []);
    }

    public function test_inactive_municipio_allowed_for_punto_acopio(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $destino = Municipio::query()->where('activo', true)->firstOrFail();
        $inactivo = Municipio::query()->where('activo', false)->orderBy('id')->first();
        if (! $inactivo) {
            $this->markTestSkipped('No hay municipios inactivos en el seed.');
        }
        $categoriaId = \App\Models\Categoria::query()->value('id');

        $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', [
            'municipio_id' => $destino->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Acopio en municipio inactivo UI',
            'resumen' => 'Los puntos pueden usar municipios fuera del catálogo activo.',
            'historia' => ['Historia mínima.'],
            'urgencia' => 'media',
            'lugar_convite' => 'Salón',
            'persona_responsable' => 'Vecino',
            'quien_respalda' => 'JAC',
            'telefono_contacto' => '+57 300 000 0000',
            'items' => [
                ['nombre' => 'Kits', 'unidad' => 'unid', 'cantidad_meta' => 10],
            ],
            'puntos_acopio' => [
                [
                    'municipio_id' => $inactivo->id,
                    'nombre' => 'Punto remoto',
                    'direccion' => 'Calle remota 1',
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.puntos_acopio.0.municipio.id', $inactivo->id);
    }

    private function municipioByNombreLike(string $needle): ?Municipio
    {
        return Municipio::query()
            ->where('nombre', 'like', '%'.$needle.'%')
            ->orderBy('id')
            ->first();
    }
}
