<?php

namespace Tests\Feature;

use App\Models\Iniciativa;
use App\Models\IniciativaAvance;
use App\Models\IniciativaItem;
use App\Models\Municipio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 (avances-convite): general-vs-item validation + monotonic
 * percentage floor (D-C). Spec capability `iniciativa-avances`.
 */
class IniciativaAvanceValidacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function crearIniciativaConItem(User $autor): array
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();

        $iniciativa = Iniciativa::factory()->create([
            'user_id' => $autor->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
        ]);

        $item = IniciativaItem::query()->create([
            'iniciativa_id' => $iniciativa->id,
            'nombre' => 'Sacos de arroz',
            'unidad' => 'saco',
            'cantidad_meta' => 100,
            'cantidad_aportada' => 0,
            'orden' => 1,
        ]);

        return [$iniciativa, $item];
    }

    private function autor(): User
    {
        return User::query()->where('email', 'member@convites.test')->firstOrFail();
    }

    public function test_tipo_general_prohibe_campos_de_item(): void
    {
        $autor = $this->autor();
        [$iniciativa] = $this->crearIniciativaConItem($autor);

        $response = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances", [
                'titulo' => 'Avance general',
                'tipo' => 'general',
                'iniciativa_item_id' => 1,
                'porcentaje' => 50,
                'publicado' => false,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['iniciativa_item_id', 'porcentaje']);
    }

    public function test_tipo_item_requiere_item_y_porcentaje(): void
    {
        $autor = $this->autor();
        [$iniciativa] = $this->crearIniciativaConItem($autor);

        $response = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances", [
                'titulo' => 'Avance de ítem',
                'tipo' => 'item',
                'publicado' => false,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['iniciativa_item_id', 'porcentaje']);
    }

    public function test_primer_avance_publicado_sin_piso_previo_tiene_exito(): void
    {
        $autor = $this->autor();
        [$iniciativa, $item] = $this->crearIniciativaConItem($autor);

        $response = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances", [
                'titulo' => 'Primer avance',
                'tipo' => 'item',
                'iniciativa_item_id' => $item->id,
                'porcentaje' => 10,
                'publicado' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.porcentaje', 10);
    }

    public function test_segundo_avance_que_iguala_o_supera_el_piso_tiene_exito(): void
    {
        $autor = $this->autor();
        [$iniciativa, $item] = $this->crearIniciativaConItem($autor);

        IniciativaAvance::factory()->publicado()->create([
            'iniciativa_id' => $iniciativa->id,
            'iniciativa_item_id' => $item->id,
            'user_id' => $autor->id,
            'porcentaje' => 40,
        ]);

        $response = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances", [
                'titulo' => 'Avance que iguala el piso',
                'tipo' => 'item',
                'iniciativa_item_id' => $item->id,
                'porcentaje' => 40,
                'publicado' => true,
            ]);

        $response->assertCreated();
    }

    public function test_segundo_avance_bajo_el_piso_es_rechazado(): void
    {
        $autor = $this->autor();
        [$iniciativa, $item] = $this->crearIniciativaConItem($autor);

        IniciativaAvance::factory()->publicado()->create([
            'iniciativa_id' => $iniciativa->id,
            'iniciativa_item_id' => $item->id,
            'user_id' => $autor->id,
            'porcentaje' => 40,
        ]);

        $response = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances", [
                'titulo' => 'Avance bajo el piso',
                'tipo' => 'item',
                'iniciativa_item_id' => $item->id,
                'porcentaje' => 30,
                'publicado' => true,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['porcentaje']);
    }

    public function test_borrador_bajo_el_piso_no_es_rechazado(): void
    {
        $autor = $this->autor();
        [$iniciativa, $item] = $this->crearIniciativaConItem($autor);

        IniciativaAvance::factory()->publicado()->create([
            'iniciativa_id' => $iniciativa->id,
            'iniciativa_item_id' => $item->id,
            'user_id' => $autor->id,
            'porcentaje' => 40,
        ]);

        $response = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances", [
                'titulo' => 'Borrador bajo el piso',
                'tipo' => 'item',
                'iniciativa_item_id' => $item->id,
                'porcentaje' => 5,
                'publicado' => false,
            ]);

        $response->assertCreated();
    }

    public function test_editar_el_max_holder_hacia_abajo_recalcula_el_piso_para_el_siguiente_avance(): void
    {
        $autor = $this->autor();
        [$iniciativa, $item] = $this->crearIniciativaConItem($autor);

        $maxHolder = IniciativaAvance::factory()->publicado()->create([
            'iniciativa_id' => $iniciativa->id,
            'iniciativa_item_id' => $item->id,
            'user_id' => $autor->id,
            'porcentaje' => 40,
        ]);

        $this->actingAs($autor, 'sanctum')
            ->patchJson("/api/iniciativas/{$iniciativa->uuid}/avances/{$maxHolder->id}", [
                'titulo' => $maxHolder->titulo,
                'tipo' => 'item',
                'iniciativa_item_id' => $item->id,
                'porcentaje' => 20,
                'publicado' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.porcentaje', 20);

        // Now the floor for a NEW avance on this item is 20, not 40.
        $response = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances", [
                'titulo' => 'Avance tras bajar el piso',
                'tipo' => 'item',
                'iniciativa_item_id' => $item->id,
                'porcentaje' => 25,
                'publicado' => true,
            ]);

        $response->assertCreated();
    }

    public function test_eliminar_el_max_holder_recalcula_el_piso(): void
    {
        $autor = $this->autor();
        [$iniciativa, $item] = $this->crearIniciativaConItem($autor);

        $maxHolder = IniciativaAvance::factory()->publicado()->create([
            'iniciativa_id' => $iniciativa->id,
            'iniciativa_item_id' => $item->id,
            'user_id' => $autor->id,
            'porcentaje' => 40,
        ]);

        IniciativaAvance::factory()->publicado()->create([
            'iniciativa_id' => $iniciativa->id,
            'iniciativa_item_id' => $item->id,
            'user_id' => $autor->id,
            'porcentaje' => 15,
        ]);

        $this->actingAs($autor, 'sanctum')
            ->deleteJson("/api/iniciativas/{$iniciativa->uuid}/avances/{$maxHolder->id}")
            ->assertNoContent();

        $response = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances", [
                'titulo' => 'Avance tras eliminar el max-holder',
                'tipo' => 'item',
                'iniciativa_item_id' => $item->id,
                'porcentaje' => 18,
                'publicado' => true,
            ]);

        $response->assertCreated();
    }
}
