<?php

namespace Tests\Feature;

use App\Models\Iniciativa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * [P53] Wizard de creación por pasos (wizard_paso) — parte 1.
 */
class IniciativaWizardPasoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function basePayload(): array
    {
        $municipio = \App\Models\Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = \App\Models\Categoria::query()->value('id');

        return [
            'municipio_id' => $municipio->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Convite wizard_paso',
            'resumen' => 'Resumen corto de prueba para el wizard por pasos.',
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
        ];
    }

    public function test_create_persists_and_returns_wizard_paso(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();

        $payload = $this->basePayload();
        $payload['wizard_paso'] = 3;

        $create = $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', $payload);

        $create->assertCreated()->assertJsonPath('data.wizard_paso', 3);

        $id = $create->json('data.id');
        $this->assertSame(3, Iniciativa::query()->whereKey($id)->value('wizard_paso'));
    }

    public function test_create_without_wizard_paso_keeps_working_and_is_null(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();

        $create = $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', $this->basePayload());

        $create->assertCreated()->assertJsonPath('data.wizard_paso', null);

        $id = $create->json('data.id');
        $this->assertNull(Iniciativa::query()->whereKey($id)->value('wizard_paso'));
    }

    public function test_update_can_advance_wizard_paso(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();

        $payload = $this->basePayload();
        $payload['wizard_paso'] = 1;

        $create = $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', $payload);
        $create->assertCreated()->assertJsonPath('data.wizard_paso', 1);

        $id = $create->json('data.id');
        $version = $create->json('data.version');

        $updatePayload = $this->basePayload();
        unset($updatePayload['items']);
        $updatePayload['items'] = $payload['items'];
        $updatePayload['version'] = $version;
        $updatePayload['wizard_paso'] = 4;

        $this->actingAs($member, 'sanctum')
            ->putJson("/api/iniciativas/{$id}", $updatePayload)
            ->assertOk()
            ->assertJsonPath('data.wizard_paso', 4);

        $this->assertSame(4, Iniciativa::query()->whereKey($id)->value('wizard_paso'));
    }

    public function test_wizard_paso_out_of_range_is_rejected(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();

        $payload = $this->basePayload();
        $payload['wizard_paso'] = 7;

        $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['wizard_paso']);
    }

    public function test_wizard_paso_zero_is_rejected(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();

        $payload = $this->basePayload();
        $payload['wizard_paso'] = 0;

        $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['wizard_paso']);
    }

    public function test_wizard_paso_non_integer_is_rejected(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();

        $payload = $this->basePayload();
        $payload['wizard_paso'] = 'dos';

        $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['wizard_paso']);
    }

    public function test_wizard_paso_1_autosave_does_not_require_later_step_fields(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $categoriaId = \App\Models\Categoria::query()->value('id');

        $payload = [
            'wizard_paso' => 1,
            'categoria_id' => $categoriaId,
            'titulo' => 'Convite paso 1',
            'resumen' => 'Resumen corto de prueba para el paso 1.',
            'historia' => ['Primera parte de la historia comunitaria.'],
            'urgencia' => 'alta',
        ];

        $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', $payload)
            ->assertCreated()
            ->assertJsonPath('data.wizard_paso', 1);
    }

    public function test_wizard_paso_2_autosave_does_not_require_items_or_verificacion_fields(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $municipio = \App\Models\Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = \App\Models\Categoria::query()->value('id');

        $payload = [
            'wizard_paso' => 1,
            'categoria_id' => $categoriaId,
            'titulo' => 'Convite paso 2',
            'resumen' => 'Resumen corto de prueba para el paso 2.',
            'historia' => ['Primera parte de la historia comunitaria.'],
            'urgencia' => 'alta',
        ];

        $create = $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', $payload);
        $create->assertCreated();

        $id = $create->json('data.id');
        $version = $create->json('data.version');

        $updatePayload = $payload;
        $updatePayload['wizard_paso'] = 2;
        $updatePayload['version'] = $version;
        $updatePayload['municipio_id'] = $municipio->id;
        $updatePayload['lugar_convite'] = 'Salón comunal de prueba';

        $this->actingAs($member, 'sanctum')
            ->putJson("/api/iniciativas/{$id}", $updatePayload)
            ->assertOk()
            ->assertJsonPath('data.wizard_paso', 2);
    }

    public function test_wizard_paso_3_autosave_does_not_require_verificacion_fields(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $municipio = \App\Models\Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = \App\Models\Categoria::query()->value('id');

        $payload = [
            'wizard_paso' => 2,
            'categoria_id' => $categoriaId,
            'titulo' => 'Convite paso 3',
            'resumen' => 'Resumen corto de prueba para el paso 3.',
            'historia' => ['Primera parte de la historia comunitaria.'],
            'urgencia' => 'alta',
            'municipio_id' => $municipio->id,
            'lugar_convite' => 'Salón comunal de prueba',
        ];

        $create = $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', $payload);
        $create->assertCreated();

        $id = $create->json('data.id');
        $version = $create->json('data.version');

        $updatePayload = $payload;
        $updatePayload['wizard_paso'] = 3;
        $updatePayload['version'] = $version;
        $updatePayload['items'] = [
            ['nombre' => 'Cemento', 'unidad' => 'bultos', 'cantidad_meta' => 10],
        ];

        $this->actingAs($member, 'sanctum')
            ->putJson("/api/iniciativas/{$id}", $updatePayload)
            ->assertOk()
            ->assertJsonPath('data.wizard_paso', 3);
    }

    public function test_wizard_paso_6_autosave_still_requires_persona_responsable(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $municipio = \App\Models\Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = \App\Models\Categoria::query()->value('id');

        $payload = [
            'wizard_paso' => 3,
            'categoria_id' => $categoriaId,
            'titulo' => 'Convite paso 6',
            'resumen' => 'Resumen corto de prueba para el paso 6.',
            'historia' => ['Primera parte de la historia comunitaria.'],
            'urgencia' => 'alta',
            'municipio_id' => $municipio->id,
            'lugar_convite' => 'Salón comunal de prueba',
            'items' => [
                ['nombre' => 'Cemento', 'unidad' => 'bultos', 'cantidad_meta' => 10],
            ],
        ];

        $create = $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', $payload);
        $create->assertCreated();

        $id = $create->json('data.id');
        $version = $create->json('data.version');

        $updatePayload = $payload;
        $updatePayload['wizard_paso'] = 6;
        $updatePayload['version'] = $version;
        // persona_responsable, quien_respalda y telefono_contacto siguen ausentes.

        $this->actingAs($member, 'sanctum')
            ->putJson("/api/iniciativas/{$id}", $updatePayload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['persona_responsable', 'quien_respalda', 'telefono_contacto']);
    }

    public function test_wizard_paso_omitted_still_requires_items(): void
    {
        $member = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $municipio = \App\Models\Municipio::query()->where('activo', true)->firstOrFail();
        $categoriaId = \App\Models\Categoria::query()->value('id');

        $payload = [
            'municipio_id' => $municipio->id,
            'categoria_id' => $categoriaId,
            'titulo' => 'Convite sin wizard_paso',
            'resumen' => 'Resumen corto de prueba sin wizard_paso.',
            'historia' => ['Primera parte de la historia comunitaria.'],
            'urgencia' => 'alta',
            'lugar_convite' => 'Salón comunal de prueba',
            'persona_responsable' => 'Vecino Prueba',
            'quien_respalda' => 'JAC Prueba',
            'telefono_contacto' => '+57 300 111 2233',
            // items ausente a propósito: sin wizard_paso debe exigirse (comportamiento estricto por defecto).
        ];

        $this->actingAs($member, 'sanctum')->postJson('/api/iniciativas', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }
}
