<?php

namespace Tests\Feature;

use App\Models\Iniciativa;
use App\Models\IniciativaAvance;
use App\Models\Municipio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 (avances-convite): 7 endpoints happy path, uuid addressing (D-D),
 * cross-iniciativa scoping, permissions, and the edit-after-notify invariant.
 */
class IniciativaAvanceApiTest extends TestCase
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

    // --- Create / read -------------------------------------------------

    public function test_autor_crea_avance_general_y_recibe_el_recurso_completo(): void
    {
        $autor = $this->autor();
        $iniciativa = $this->crearIniciativaDe($autor);

        $response = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances", [
                'titulo' => 'Reporte general de avance',
                'cuerpo' => 'Ya recolectamos varias donaciones.',
                'tipo' => 'general',
                'publicado' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.tipo', 'general')
            ->assertJsonPath('data.titulo', 'Reporte general de avance')
            ->assertJsonPath('data.item', null)
            ->assertJsonPath('data.porcentaje', null);

        $this->assertDatabaseCount('iniciativa_avances', 1);
    }

    public function test_uuid_invalido_da_404_en_cualquier_ruta_de_avances(): void
    {
        $this->getJson('/api/iniciativas/no-existe-este-uuid/avances')
            ->assertNotFound();

        $this->getJson('/api/iniciativas/no-existe-este-uuid/avances/algun-slug')
            ->assertNotFound();
    }

    public function test_listado_publico_solo_devuelve_publicados_ordenados_desc(): void
    {
        $autor = $this->autor();
        $iniciativa = $this->crearIniciativaDe($autor);

        $borrador = IniciativaAvance::factory()->create([
            'iniciativa_id' => $iniciativa->id,
            'user_id' => $autor->id,
            'titulo' => 'Borrador',
            'slug' => 'borrador',
        ]);

        $publicadoViejo = IniciativaAvance::factory()->create([
            'iniciativa_id' => $iniciativa->id,
            'user_id' => $autor->id,
            'titulo' => 'Publicado viejo',
            'slug' => 'publicado-viejo',
            'publicado_at' => now()->subDays(2),
        ]);

        $publicadoNuevo = IniciativaAvance::factory()->create([
            'iniciativa_id' => $iniciativa->id,
            'user_id' => $autor->id,
            'titulo' => 'Publicado nuevo',
            'slug' => 'publicado-nuevo',
            'publicado_at' => now(),
        ]);

        $response = $this->getJson("/api/iniciativas/{$iniciativa->uuid}/avances");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $publicadoNuevo->id)
            ->assertJsonPath('data.1.id', $publicadoViejo->id);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($borrador->id));
    }

    public function test_show_publico_por_slug_devuelve_solo_publicados(): void
    {
        $autor = $this->autor();
        $iniciativa = $this->crearIniciativaDe($autor);

        $publicado = IniciativaAvance::factory()->publicado()->create([
            'iniciativa_id' => $iniciativa->id,
            'user_id' => $autor->id,
            'slug' => 'avance-publicado',
        ]);

        $this->getJson("/api/iniciativas/{$iniciativa->uuid}/avances/avance-publicado")
            ->assertOk()
            ->assertJsonPath('data.id', $publicado->id);

        $borrador = IniciativaAvance::factory()->create([
            'iniciativa_id' => $iniciativa->id,
            'user_id' => $autor->id,
            'slug' => 'avance-borrador',
        ]);

        $this->getJson("/api/iniciativas/{$iniciativa->uuid}/avances/avance-borrador")
            ->assertNotFound();
    }

    // --- Update / delete -------------------------------------------------

    public function test_autor_actualiza_su_avance(): void
    {
        $autor = $this->autor();
        $iniciativa = $this->crearIniciativaDe($autor);

        $avance = IniciativaAvance::factory()->create([
            'iniciativa_id' => $iniciativa->id,
            'user_id' => $autor->id,
            'titulo' => 'Título original',
        ]);

        $this->actingAs($autor, 'sanctum')
            ->patchJson("/api/iniciativas/{$iniciativa->uuid}/avances/{$avance->id}", [
                'titulo' => 'Título editado',
                'cuerpo' => 'Cuerpo editado.',
                'tipo' => 'general',
                'publicado' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.titulo', 'Título editado')
            ->assertJsonPath('data.cuerpo', 'Cuerpo editado.');

        $this->assertNotNull($avance->fresh()->publicado_at);
    }

    public function test_avance_id_de_otra_iniciativa_da_404(): void
    {
        $autor = $this->autor();
        $iniciativaA = $this->crearIniciativaDe($autor);
        $iniciativaB = $this->crearIniciativaDe($autor);

        $avance = IniciativaAvance::factory()->create([
            'iniciativa_id' => $iniciativaA->id,
            'user_id' => $autor->id,
        ]);

        $this->actingAs($autor, 'sanctum')
            ->patchJson("/api/iniciativas/{$iniciativaB->uuid}/avances/{$avance->id}", [
                'titulo' => 'No debería aplicar',
                'tipo' => 'general',
            ])
            ->assertNotFound();

        $this->actingAs($autor, 'sanctum')
            ->deleteJson("/api/iniciativas/{$iniciativaB->uuid}/avances/{$avance->id}")
            ->assertNotFound();
    }

    public function test_no_autor_sin_permiso_de_moderacion_recibe_403(): void
    {
        $autor = $this->autor();
        $iniciativa = $this->crearIniciativaDe($autor);

        $tercero = User::factory()->create();
        $tercero->assignRole('member');

        $this->actingAs($tercero, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances", [
                'titulo' => 'Intento de tercero',
                'tipo' => 'general',
                'publicado' => false,
            ])
            ->assertForbidden();

        $avance = IniciativaAvance::factory()->create([
            'iniciativa_id' => $iniciativa->id,
            'user_id' => $autor->id,
        ]);

        $this->actingAs($tercero, 'sanctum')
            ->patchJson("/api/iniciativas/{$iniciativa->uuid}/avances/{$avance->id}", [
                'titulo' => 'Intento de edición',
                'tipo' => 'general',
            ])
            ->assertForbidden();

        $this->actingAs($tercero, 'sanctum')
            ->deleteJson("/api/iniciativas/{$iniciativa->uuid}/avances/{$avance->id}")
            ->assertForbidden();

        $this->assertDatabaseCount('iniciativa_avances', 1);
    }

    public function test_moderador_de_su_municipio_puede_mutar_avance_ajeno(): void
    {
        $autor = $this->autor();
        $iniciativa = $this->crearIniciativaDe($autor);

        $moderator = User::query()->where('email', 'moderator@convites.test')->firstOrFail();
        $moderator->municipiosAsignados()->syncWithoutDetaching([$iniciativa->municipio_id]);

        $this->actingAs($moderator, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances", [
                'titulo' => 'Avance creado por moderador',
                'tipo' => 'general',
                'publicado' => true,
            ])
            ->assertCreated();
    }

    public function test_eliminar_avance_usa_soft_delete(): void
    {
        $autor = $this->autor();
        $iniciativa = $this->crearIniciativaDe($autor);

        $avance = IniciativaAvance::factory()->create([
            'iniciativa_id' => $iniciativa->id,
            'user_id' => $autor->id,
        ]);

        $this->actingAs($autor, 'sanctum')
            ->deleteJson("/api/iniciativas/{$iniciativa->uuid}/avances/{$avance->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('iniciativa_avances', ['id' => $avance->id]);
    }

    // --- Edit after notification already sent (Spec: no re-notify) ------

    public function test_editar_avance_ya_notificado_no_limpia_ni_toca_notificado_at(): void
    {
        $autor = $this->autor();
        $iniciativa = $this->crearIniciativaDe($autor);

        $notificadoAt = now()->subHour();
        $avance = IniciativaAvance::factory()->publicado()->create([
            'iniciativa_id' => $iniciativa->id,
            'user_id' => $autor->id,
            'notificar_aportantes' => true,
            'notificado_at' => $notificadoAt,
        ]);

        $this->actingAs($autor, 'sanctum')
            ->patchJson("/api/iniciativas/{$iniciativa->uuid}/avances/{$avance->id}", [
                'titulo' => 'Contenido editado post-notificación',
                'tipo' => 'general',
                'publicado' => true,
                'notificar_aportantes' => true,
            ])
            ->assertOk();

        $fresh = $avance->fresh();
        $this->assertNotNull($fresh->notificado_at);
        $this->assertSame(
            $notificadoAt->toDateTimeString(),
            $fresh->notificado_at->toDateTimeString(),
        );
    }
}
