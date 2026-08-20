<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Iniciativa;
use App\Models\Municipio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proveedores de una iniciativa — contactos de pago/entrega asociados al convite.
 */
class IniciativaProveedoresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_create_iniciativa_with_proveedores(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = Categoria::query()->value('id');

        $create = $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', [
            'municipio_id' => $municipio->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Convite con proveedores',
            'resumen' => 'Prueba de creación con proveedores.',
            'historia' => ['Historia mínima.'],
            'urgencia' => 'media',
            'lugar_convite' => 'Salón',
            'persona_responsable' => 'Vecino',
            'quien_respalda' => 'JAC',
            'telefono_contacto' => '+57 300 000 0000',
            'items' => [
                ['nombre' => 'Cemento', 'unidad' => 'bultos', 'cantidad_meta' => 10],
            ],
            'proveedores' => [
                [
                    'nombre' => 'Ferretería El Tornillo',
                    'direccion' => 'Calle 10 #5-20',
                    'ciudad' => 'Quibdó',
                    'correo' => 'contacto@eltornillo.test',
                    'celular' => '+57 310 111 2233',
                    'instrucciones_pago' => 'Transferencia a cuenta de ahorros 123-456, confirmar por WhatsApp.',
                ],
                [
                    'nombre' => 'Depósito San José',
                    'instrucciones_pago' => 'Pago en efectivo contra entrega.',
                ],
            ],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.titulo', 'Convite con proveedores')
            ->assertJsonCount(2, 'data.proveedores')
            ->assertJsonPath('data.proveedores.0.nombre', 'Ferretería El Tornillo')
            ->assertJsonPath('data.proveedores.0.direccion', 'Calle 10 #5-20')
            ->assertJsonPath('data.proveedores.0.ciudad', 'Quibdó')
            ->assertJsonPath('data.proveedores.0.correo', 'contacto@eltornillo.test')
            ->assertJsonPath('data.proveedores.0.celular', '+57 310 111 2233')
            ->assertJsonPath('data.proveedores.0.instrucciones_pago', 'Transferencia a cuenta de ahorros 123-456, confirmar por WhatsApp.')
            ->assertJsonPath('data.proveedores.1.nombre', 'Depósito San José')
            ->assertJsonPath('data.proveedores.1.direccion', null)
            ->assertJsonPath('data.proveedores.1.instrucciones_pago', 'Pago en efectivo contra entrega.');

        $id = $create->json('data.id');
        $this->assertSame(2, Iniciativa::query()->findOrFail($id)->proveedores()->count());
    }

    public function test_create_without_proveedores_still_works(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = Categoria::query()->value('id');

        $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', [
            'municipio_id' => $municipio->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Sin proveedores',
            'resumen' => 'Compatibilidad: create sin proveedores sigue válido.',
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
            ->assertJsonPath('data.proveedores', []);
    }

    public function test_nombre_is_required(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = Categoria::query()->value('id');

        $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', [
            'municipio_id' => $municipio->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Falta nombre proveedor',
            'resumen' => 'Debe fallar validación.',
            'historia' => ['Historia mínima.'],
            'urgencia' => 'baja',
            'lugar_convite' => 'Salón',
            'persona_responsable' => 'Vecino',
            'quien_respalda' => 'JAC',
            'telefono_contacto' => '+57 300 000 0000',
            'items' => [
                ['nombre' => 'Clavos', 'unidad' => 'cajas', 'cantidad_meta' => 3],
            ],
            'proveedores' => [
                [
                    'instrucciones_pago' => 'Pago en efectivo.',
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['proveedores.0.nombre']);
    }

    public function test_instrucciones_pago_is_required(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = Categoria::query()->value('id');

        $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', [
            'municipio_id' => $municipio->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Falta instrucciones de pago',
            'resumen' => 'Debe fallar validación.',
            'historia' => ['Historia mínima.'],
            'urgencia' => 'baja',
            'lugar_convite' => 'Salón',
            'persona_responsable' => 'Vecino',
            'quien_respalda' => 'JAC',
            'telefono_contacto' => '+57 300 000 0000',
            'items' => [
                ['nombre' => 'Clavos', 'unidad' => 'cajas', 'cantidad_meta' => 3],
            ],
            'proveedores' => [
                [
                    'nombre' => 'Proveedor sin instrucciones',
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['proveedores.0.instrucciones_pago']);
    }

    public function test_invalid_correo_fails_validation(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = Categoria::query()->value('id');

        $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', [
            'municipio_id' => $municipio->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Correo inválido',
            'resumen' => 'Debe fallar validación.',
            'historia' => ['Historia mínima.'],
            'urgencia' => 'baja',
            'lugar_convite' => 'Salón',
            'persona_responsable' => 'Vecino',
            'quien_respalda' => 'JAC',
            'telefono_contacto' => '+57 300 000 0000',
            'items' => [
                ['nombre' => 'Clavos', 'unidad' => 'cajas', 'cantidad_meta' => 3],
            ],
            'proveedores' => [
                [
                    'nombre' => 'Proveedor correo malo',
                    'correo' => 'no-es-un-correo',
                    'instrucciones_pago' => 'Pago en efectivo.',
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['proveedores.0.correo']);
    }

    public function test_max_20_proveedores_enforced(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = Categoria::query()->value('id');

        $proveedores = [];
        for ($i = 0; $i < 21; $i++) {
            $proveedores[] = [
                'nombre' => "Proveedor {$i}",
                'instrucciones_pago' => 'Pago en efectivo.',
            ];
        }

        $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', [
            'municipio_id' => $municipio->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Demasiados proveedores',
            'resumen' => 'Debe fallar validación por máximo.',
            'historia' => ['Historia mínima.'],
            'urgencia' => 'baja',
            'lugar_convite' => 'Salón',
            'persona_responsable' => 'Vecino',
            'quien_respalda' => 'JAC',
            'telefono_contacto' => '+57 300 000 0000',
            'items' => [
                ['nombre' => 'Clavos', 'unidad' => 'cajas', 'cantidad_meta' => 3],
            ],
            'proveedores' => $proveedores,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['proveedores']);
    }

    public function test_update_sync_replaces_proveedores(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = Categoria::query()->value('id');

        $create = $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', [
            'municipio_id' => $municipio->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Sync proveedores',
            'resumen' => 'Prueba de reemplazo de proveedores en update.',
            'historia' => ['Historia mínima.'],
            'urgencia' => 'media',
            'lugar_convite' => 'Salón',
            'persona_responsable' => 'Vecino',
            'quien_respalda' => 'JAC',
            'telefono_contacto' => '+57 300 000 0000',
            'items' => [
                ['nombre' => 'Arena', 'unidad' => 'bultos', 'cantidad_meta' => 5],
            ],
            'proveedores' => [
                [
                    'nombre' => 'Proveedor A',
                    'instrucciones_pago' => 'Pago A.',
                ],
            ],
        ]);

        $create->assertCreated()->assertJsonCount(1, 'data.proveedores');
        $id = $create->json('data.id');
        $version = $create->json('data.version');

        $this->actingAs($member, 'sanctum')
            ->putJson("/api/iniciativas/{$id}", [
                'municipio_id' => $municipio->id,
                'categoria_id' => $categoriaId,
                'titulo' => 'Sync proveedores',
                'resumen' => 'Prueba de reemplazo de proveedores en update.',
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
                'proveedores' => [
                    [
                        'nombre' => 'Proveedor B',
                        'instrucciones_pago' => 'Pago B.',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.proveedores')
            ->assertJsonPath('data.proveedores.0.nombre', 'Proveedor B');

        $iniciativa = Iniciativa::query()->findOrFail($id);
        $this->assertSame(1, $iniciativa->proveedores()->count());
        $this->assertSame('Proveedor B', $iniciativa->proveedores()->first()->nombre);
    }
}
