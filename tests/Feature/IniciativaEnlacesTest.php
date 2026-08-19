<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Iniciativa;
use App\Models\Municipio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P53 (parte 3) — enlaces adicionales del convite (`enlaces[]`), distinto
 * de `enlace_externo_plataforma`/`enlace_externo_url`.
 */
class IniciativaEnlacesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function basePayload(array $overrides = []): array
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = Categoria::query()->value('id');

        return array_merge([
            'municipio_id' => $municipio->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Convite con enlaces',
            'resumen' => 'Resumen corto de prueba para el flujo de enlaces.',
            'historia' => ['Primera parte de la historia comunitaria.'],
            'urgencia' => 'alta',
            'lugar_convite' => 'Salón comunal de prueba',
            'persona_responsable' => 'Vecino Prueba',
            'quien_respalda' => 'JAC Prueba',
            'telefono_contacto' => '+57 300 111 2233',
            'items' => [
                ['nombre' => 'Cemento', 'unidad' => 'bultos', 'cantidad_meta' => 10],
            ],
        ], $overrides);
    }

    public function test_store_persiste_enlaces(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();

        $response = $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', $this->basePayload([
            'enlaces' => [
                ['titulo' => 'Grupo de WhatsApp', 'url' => 'https://wa.me/123456', 'orden' => 1],
                ['titulo' => 'Evento en Facebook', 'url' => 'https://facebook.com/evento/1', 'orden' => 2],
            ],
        ]));

        $response->assertCreated()
            ->assertJsonCount(2, 'data.enlaces')
            ->assertJsonPath('data.enlaces.0.titulo', 'Grupo de WhatsApp')
            ->assertJsonPath('data.enlaces.0.url', 'https://wa.me/123456')
            ->assertJsonPath('data.enlaces.1.titulo', 'Evento en Facebook')
            ->assertJsonStructure(['data' => ['enlaces' => [['id', 'titulo', 'url', 'orden']]]]);

        $id = $response->json('data.id');
        $this->assertSame(2, Iniciativa::query()->findOrFail($id)->enlaces()->count());
    }

    public function test_store_sin_enlaces_devuelve_arreglo_vacio(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();

        $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', $this->basePayload())
            ->assertCreated()
            ->assertJsonPath('data.enlaces', []);
    }

    public function test_update_reemplaza_totalmente_el_set_de_enlaces(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();

        $create = $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', $this->basePayload([
            'enlaces' => [
                ['titulo' => 'Enlace viejo 1', 'url' => 'https://example.test/1'],
                ['titulo' => 'Enlace viejo 2', 'url' => 'https://example.test/2'],
                ['titulo' => 'Enlace viejo 3', 'url' => 'https://example.test/3'],
            ],
        ]));
        $create->assertCreated()->assertJsonCount(3, 'data.enlaces');

        $id = $create->json('data.id');
        $version = $create->json('data.version');

        $update = $this->actingAs($member, 'sanctum')->putJson("/api/iniciativas/{$id}", $this->basePayload([
            'version' => $version,
            'enlaces' => [
                ['titulo' => 'Enlace nuevo único', 'url' => 'https://example.test/nuevo'],
            ],
        ]));

        $update->assertOk()
            ->assertJsonCount(1, 'data.enlaces')
            ->assertJsonPath('data.enlaces.0.titulo', 'Enlace nuevo único');

        $this->assertSame(1, Iniciativa::query()->findOrFail($id)->enlaces()->count());
    }

    public function test_update_omitiendo_enlaces_no_los_borra(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();

        $create = $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', $this->basePayload([
            'enlaces' => [
                ['titulo' => 'Enlace persistente', 'url' => 'https://example.test/persistente'],
            ],
        ]));
        $create->assertCreated();

        $id = $create->json('data.id');
        $version = $create->json('data.version');

        // Update sin la clave "enlaces" en el payload: no debe tocar el set existente.
        $update = $this->actingAs($member, 'sanctum')->putJson(
            "/api/iniciativas/{$id}",
            $this->basePayload(['version' => $version]),
        );

        $update->assertOk()->assertJsonCount(1, 'data.enlaces');
        $this->assertSame(1, Iniciativa::query()->findOrFail($id)->enlaces()->count());
    }

    public function test_mas_de_20_enlaces_da_422(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();

        $enlaces = array_map(
            fn (int $i) => ['titulo' => "Enlace {$i}", 'url' => "https://example.test/{$i}"],
            range(1, 21),
        );

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/iniciativas', $this->basePayload(['enlaces' => $enlaces]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['enlaces']);
    }

    public function test_url_invalida_da_422(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/iniciativas', $this->basePayload([
                'enlaces' => [
                    ['titulo' => 'Enlace roto', 'url' => 'no-es-una-url'],
                ],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['enlaces.0.url']);
    }

    public function test_enlace_sin_titulo_da_422(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/iniciativas', $this->basePayload([
                'enlaces' => [
                    ['url' => 'https://example.test/sin-titulo'],
                ],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['enlaces.0.titulo']);
    }
}
