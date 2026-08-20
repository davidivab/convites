<?php

namespace Tests\Feature;

use App\Enums\EstadoIniciativa;
use App\Enums\EstadoAporte;
use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IniciativaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_public_can_list_and_view_published_iniciativas(): void
    {
        $list = $this->getJson('/api/iniciativas');
        $list->assertOk()->assertJsonStructure(['data']);

        $slug = Iniciativa::query()
            ->where('estado', EstadoIniciativa::Publicada)
            ->value('slug');

        $this->assertNotNull($slug);

        $this->getJson('/api/iniciativas/'.$slug)
            ->assertOk()
            ->assertJsonPath('data.slug', $slug)
            ->assertJsonPath('data.lugar_exacto', null);
    }

    public function test_member_can_create_submit_and_moderator_can_approve(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $municipio = \App\Models\Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = \App\Models\Categoria::query()->value('id');

        $create = $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', [
            'municipio_id' => $municipio->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Convite de prueba API',
            'resumen' => 'Resumen corto de prueba para el flujo de API.',
            'historia' => ['Primera parte de la historia comunitaria.'],
            'urgencia' => 'alta',
            'lugar_convite' => 'Salón comunal de prueba',
            'lugar_exacto' => 'Calle 1 #2-3',
            'persona_responsable' => 'Vecino Prueba',
            'quien_respalda' => 'JAC Prueba',
            'telefono_contacto' => '+57 300 111 2233',
            'items' => [
                ['nombre' => 'Cemento', 'unidad' => 'bultos', 'cantidad_meta' => 10],
            ],
        ]);

        $create->assertCreated()->assertJsonPath('data.estado', 'borrador');
        $id = $create->json('data.id');

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/iniciativas/{$id}/enviar-revision")
            ->assertOk()
            ->assertJsonPath('data.estado', 'en_revision');

        // Evita que el guard Sanctum deje cacheado al member entre requests del mismo test.
        $this->app['auth']->forgetGuards();

        $moderator = User::query()->where('email', 'moderator@convites.test')->firstOrFail();
        $moderator->municipiosAsignados()->syncWithoutDetaching([$municipio->id]);

        $this->actingAs($moderator, 'sanctum')
            ->postJson("/api/moderacion/iniciativas/{$id}/aprobar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'publicada');
    }

    public function test_member_can_create_aporte_with_idempotency(): void
    {
        $aportante = User::query()->create([
            'name' => 'Aportante Prueba',
            'email' => 'aportante@convites.test',
            'password' => 'password',
            'inicial' => 'P',
            'acepta_terminos_at' => now(),
            'acepta_descargo_at' => now(),
        ]);
        $aportante->assignRole('member');

        $token = $this->loginToken('aportante@convites.test');

        $iniciativa = Iniciativa::query()
            ->where('estado', EstadoIniciativa::Publicada)
            ->with('items')
            ->firstOrFail();

        /** @var IniciativaItem $item */
        $item = $iniciativa->items->firstOrFail();

        $payload = [
            'asiste_al_convite' => true,
            'client_request_id' => 'test-idem-001',
            'items' => [
                ['iniciativa_item_id' => $item->id, 'cantidad' => 2],
            ],
        ];

        $first = $this->withToken($token)
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", $payload);
        $first->assertCreated()->assertJsonPath('data.estado', EstadoAporte::Confirmado->value);

        $second = $this->withToken($token)
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", $payload);
        $second->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));

        $this->withToken($token)
            ->getJson('/api/iniciativas/'.$iniciativa->slug)
            ->assertOk()
            ->assertJsonPath('data.lugar_exacto', $iniciativa->lugar_exacto);
    }

    public function test_imagen_path_is_resolved_to_absolute_url(): void
    {
        $iniciativa = Iniciativa::query()
            ->where('estado', EstadoIniciativa::Publicada)
            ->firstOrFail();
        $iniciativa->imagen_path = 'iniciativas/foto-demo.jpg';
        $iniciativa->save();

        $expected = \Illuminate\Support\Facades\Storage::disk(\App\Support\UploadDisk::name())
            ->url('iniciativas/foto-demo.jpg');

        $this->getJson('/api/iniciativas/'.$iniciativa->slug)
            ->assertOk()
            ->assertJsonPath('data.imagen_path', $expected);

        $this->assertStringStartsWith('http', $expected);
    }

    public function test_catalogs_and_centros_are_public(): void
    {
        $this->getJson('/api/catalogos/zonas')->assertOk()->assertJsonStructure(['data']);
        $this->getJson('/api/centros')->assertOk()->assertJsonStructure(['data']);
        $this->getJson('/api/profesionales')->assertOk()->assertJsonStructure(['data']);
    }

    public function test_update_rejects_stale_version_with_conflict(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $zonaId = \App\Models\Zona::query()->value('id');
        $categoriaId = \App\Models\Categoria::query()->value('id');

        $create = $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', [
            'zona_id' => $zonaId,
            'categoria_id' => $categoriaId,
            'titulo' => 'Convite optimistic lock',
            'resumen' => 'Resumen para probar conflicto de versión concurrente.',
            'historia' => ['Historia mínima de prueba.'],
            'urgencia' => 'media',
            'lugar_convite' => 'Salón comunal',
            'persona_responsable' => 'Vecino',
            'quien_respalda' => 'JAC',
            'telefono_contacto' => '+57 300 000 0000',
            'items' => [
                ['nombre' => 'Arena', 'unidad' => 'bultos', 'cantidad_meta' => 5],
            ],
        ]);

        $create->assertCreated();
        $id = $create->json('data.id');
        $version = $create->json('data.version');
        $this->assertSame(1, $version);

        $payload = [
            'zona_id' => $zonaId,
            'categoria_id' => $categoriaId,
            'titulo' => 'Convite optimistic lock editado',
            'resumen' => 'Resumen para probar conflicto de versión concurrente.',
            'historia' => ['Historia mínima de prueba.'],
            'urgencia' => 'media',
            'lugar_convite' => 'Salón comunal',
            'persona_responsable' => 'Vecino',
            'quien_respalda' => 'JAC',
            'telefono_contacto' => '+57 300 000 0000',
            'version' => $version,
            'items' => [
                ['nombre' => 'Arena', 'unidad' => 'bultos', 'cantidad_meta' => 8],
            ],
        ];

        $this->actingAs($member, 'sanctum')
            ->putJson("/api/iniciativas/{$id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.titulo', 'Convite optimistic lock editado');

        // Segunda edición con la versión vieja (1) → 409
        $this->actingAs($member, 'sanctum')
            ->putJson("/api/iniciativas/{$id}", array_merge($payload, [
                'titulo' => 'No debería guardar',
                'version' => 1,
            ]))
            ->assertStatus(409);

        $this->assertSame(2, Iniciativa::query()->whereKey($id)->value('version'));
        $this->assertSame(
            'Convite optimistic lock editado',
            Iniciativa::query()->whereKey($id)->value('titulo'),
        );
    }

    public function test_item_persists_descripcion_and_valor_unitario_aprox_with_computed_totals(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $municipio = \App\Models\Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = \App\Models\Categoria::query()->value('id');

        $create = $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', [
            'municipio_id' => $municipio->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Convite con valor aproximado de ítems',
            'resumen' => 'Resumen corto de prueba para valor aproximado.',
            'historia' => ['Primera parte de la historia comunitaria.'],
            'urgencia' => 'alta',
            'lugar_convite' => 'Salón comunal de prueba',
            'persona_responsable' => 'Vecino Prueba',
            'quien_respalda' => 'JAC Prueba',
            'telefono_contacto' => '+57 300 111 2233',
            'items' => [
                [
                    'nombre' => 'Cemento',
                    'unidad' => 'bultos',
                    'cantidad_meta' => 10,
                    'descripcion' => 'Se consigue en la ferretería del centro, pedir tipo gris.',
                    'valor_unitario_aprox' => 25000,
                ],
            ],
        ]);

        $create->assertCreated();
        $create->assertJsonPath('data.items.0.descripcion', 'Se consigue en la ferretería del centro, pedir tipo gris.');
        $create->assertJsonPath('data.items.0.valor_unitario_aprox', 25000);
        $create->assertJsonPath('data.items.0.valor_meta_aprox', 250000);
        $create->assertJsonPath('data.items.0.valor_aportado_aprox', 0);

        $item = IniciativaItem::query()->where('iniciativa_id', $create->json('data.id'))->firstOrFail();
        $this->assertSame('Se consigue en la ferretería del centro, pedir tipo gris.', $item->descripcion);
        $this->assertEquals(25000, $item->valor_unitario_aprox);
    }

    public function test_item_sin_descripcion_ni_valor_unitario_aprox_queda_null_sin_defaultear_a_cero(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $municipio = \App\Models\Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = \App\Models\Categoria::query()->value('id');

        $create = $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', [
            'municipio_id' => $municipio->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Convite sin valor aproximado de ítems',
            'resumen' => 'Resumen corto de prueba sin valor aproximado.',
            'historia' => ['Primera parte de la historia comunitaria.'],
            'urgencia' => 'alta',
            'lugar_convite' => 'Salón comunal de prueba',
            'persona_responsable' => 'Vecino Prueba',
            'quien_respalda' => 'JAC Prueba',
            'telefono_contacto' => '+57 300 111 2233',
            'items' => [
                ['nombre' => 'Arena', 'unidad' => 'bultos', 'cantidad_meta' => 5],
            ],
        ]);

        $create->assertCreated();
        $create->assertJsonPath('data.items.0.descripcion', null);
        $create->assertJsonPath('data.items.0.valor_unitario_aprox', null);
        $create->assertJsonPath('data.items.0.valor_meta_aprox', null);
        $create->assertJsonPath('data.items.0.valor_aportado_aprox', null);
    }

    public function test_valor_unitario_aprox_negativo_es_rechazado(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $municipio = \App\Models\Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = \App\Models\Categoria::query()->value('id');

        $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', [
            'municipio_id' => $municipio->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Convite con valor negativo',
            'resumen' => 'Resumen corto de prueba con valor negativo.',
            'historia' => ['Primera parte de la historia comunitaria.'],
            'urgencia' => 'alta',
            'lugar_convite' => 'Salón comunal de prueba',
            'persona_responsable' => 'Vecino Prueba',
            'quien_respalda' => 'JAC Prueba',
            'telefono_contacto' => '+57 300 111 2233',
            'items' => [
                ['nombre' => 'Cemento', 'unidad' => 'bultos', 'cantidad_meta' => 10, 'valor_unitario_aprox' => -100],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.valor_unitario_aprox']);
    }

    private function loginToken(string $email): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password',
        ])->json('token');
    }
}
