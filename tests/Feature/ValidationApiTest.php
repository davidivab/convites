<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use App\Models\User;
use App\Models\Zona;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validación negativa (422) con factories — sin depender de DemoDataSeeder.
 */
class ValidationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function member(): User
    {
        $member = User::factory()->create();
        $member->forceFill([
            'acepta_terminos_at' => now(),
            'acepta_descargo_at' => now(),
        ])->save();
        $member->assignRole('member');

        return $member;
    }

    public function test_create_iniciativa_rejects_invalid_payload(): void
    {
        $member = $this->member();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/iniciativas', [
                'titulo' => '',
                'items' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['zona_id', 'categoria_id', 'titulo', 'resumen', 'historia', 'urgencia', 'lugar_convite', 'items']);
    }

    public function test_create_iniciativa_happy_path_with_factories(): void
    {
        $zona = Zona::factory()->create();
        $categoria = Categoria::factory()->create();
        $member = $this->member();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/iniciativas', [
                'zona_id' => $zona->id,
                'categoria_id' => $categoria->id,
                'titulo' => 'Convite factory',
                'resumen' => 'Resumen válido creado con factories para el happy path.',
                'historia' => ['Primera parte.'],
                'urgencia' => 'alta',
                'lugar_convite' => 'Salón comunal',
                'persona_responsable' => 'Vecino',
                'quien_respalda' => 'JAC',
                'telefono_contacto' => '+57 300 111 2233',
                'items' => [
                    ['nombre' => 'Cemento', 'unidad' => 'bultos', 'cantidad_meta' => 10],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'borrador')
            ->assertJsonPath('data.version', 1);
    }

    public function test_create_aporte_rejects_invalid_items_cantidad(): void
    {
        $member = $this->member();

        $iniciativa = Iniciativa::factory()->publicada()->create(['user_id' => $member->id]);
        IniciativaItem::query()->create([
            'iniciativa_id' => $iniciativa->id,
            'nombre' => 'Arena',
            'unidad' => 'bultos',
            'cantidad_meta' => 5,
            'cantidad_aportada' => 0,
            'orden' => 1,
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", [
                'items' => [
                    ['iniciativa_item_id' => 1, 'cantidad' => 0],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.cantidad']);
    }

    public function test_register_profesional_rejects_invalid_payload(): void
    {
        $member = $this->member();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/profesionales', [
                'nombre' => 'Sin datos',
                'email' => 'no-es-email',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['zona_id', 'area', 'titulo', 'email', 'modalidad', 'disponibilidad', 'descripcion']);
    }

    public function test_update_profile_rejects_edad_fuera_de_rango(): void
    {
        $member = $this->member();

        $this->actingAs($member, 'sanctum')
            ->putJson('/api/profile', ['edad' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['edad']);
    }
}
