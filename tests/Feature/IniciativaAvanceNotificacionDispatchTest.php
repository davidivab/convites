<?php

namespace Tests\Feature;

use App\Jobs\SendAvanceAportantesJob;
use App\Models\Iniciativa;
use App\Models\IniciativaAvance;
use App\Models\Municipio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase 4 (avances-convite), task 4.3: wires `SendAvanceAportantesJob`
 * dispatch into `IniciativaAvanceController::store()`/`update()`, guarded
 * by "publicado_at newly set && notificar_aportantes && !notificado_at".
 * Re-verifies the edit-after-notify invariant (Spec: Edit after publish
 * does not re-notify) now that the real dispatch call exists.
 */
class IniciativaAvanceNotificacionDispatchTest extends TestCase
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

    public function test_publicar_con_notificar_al_crear_encola_el_job(): void
    {
        Queue::fake();

        $autor = $this->autor();
        $iniciativa = $this->crearIniciativaDe($autor);

        $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances", [
                'titulo' => 'Avance publicado con notificación',
                'tipo' => 'general',
                'publicado' => true,
                'notificar_aportantes' => true,
            ])
            ->assertCreated();

        Queue::assertPushed(SendAvanceAportantesJob::class);
    }

    public function test_crear_borrador_no_encola_el_job(): void
    {
        Queue::fake();

        $autor = $this->autor();
        $iniciativa = $this->crearIniciativaDe($autor);

        $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances", [
                'titulo' => 'Borrador sin publicar',
                'tipo' => 'general',
                'publicado' => false,
                'notificar_aportantes' => true,
            ])
            ->assertCreated();

        Queue::assertNotPushed(SendAvanceAportantesJob::class);
    }

    public function test_crear_publicado_sin_notificar_no_encola_el_job(): void
    {
        Queue::fake();

        $autor = $this->autor();
        $iniciativa = $this->crearIniciativaDe($autor);

        $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances", [
                'titulo' => 'Publicado sin notificar',
                'tipo' => 'general',
                'publicado' => true,
                'notificar_aportantes' => false,
            ])
            ->assertCreated();

        Queue::assertNotPushed(SendAvanceAportantesJob::class);
    }

    public function test_editar_borrador_a_publicado_con_notificar_encola_el_job(): void
    {
        Queue::fake();

        $autor = $this->autor();
        $iniciativa = $this->crearIniciativaDe($autor);
        $avance = IniciativaAvance::factory()->create([
            'iniciativa_id' => $iniciativa->id,
            'user_id' => $autor->id,
            'notificar_aportantes' => true,
        ]);

        $this->actingAs($autor, 'sanctum')
            ->patchJson("/api/iniciativas/{$iniciativa->uuid}/avances/{$avance->id}", [
                'titulo' => 'Ahora publicado',
                'tipo' => 'general',
                'publicado' => true,
                'notificar_aportantes' => true,
            ])
            ->assertOk();

        Queue::assertPushed(SendAvanceAportantesJob::class, fn ($job) => $job->avance->is($avance));
    }

    public function test_editar_avance_ya_publicado_sin_reenviar_no_encola_de_nuevo(): void
    {
        Queue::fake();

        $autor = $this->autor();
        $iniciativa = $this->crearIniciativaDe($autor);
        $avance = IniciativaAvance::factory()->publicado()->create([
            'iniciativa_id' => $iniciativa->id,
            'user_id' => $autor->id,
            'notificar_aportantes' => true,
            'notificado_at' => null,
        ]);

        $this->actingAs($autor, 'sanctum')
            ->patchJson("/api/iniciativas/{$iniciativa->uuid}/avances/{$avance->id}", [
                'titulo' => 'Edición menor de contenido ya publicado',
                'tipo' => 'general',
                'publicado' => true,
                'notificar_aportantes' => true,
            ])
            ->assertOk();

        Queue::assertNotPushed(SendAvanceAportantesJob::class);
    }

    /**
     * NOTE on coverage: this test's avance is created already `->publicado()`,
     * so `$estabaPublicado` is `true` at the start of `update()` and the
     * `! $estabaPublicado` guard in the controller means
     * `despacharNotificacionSiCorresponde()` is never even called here — this
     * test only proves the outer transition guard blocks re-dispatch and that
     * `notificado_at` is left untouched by a plain edit. It does NOT exercise
     * the inner `notificado_at === null` guard inside
     * `despacharNotificacionSiCorresponde()`. That guard is only reachable
     * when `$estabaPublicado` is `false` at the start of `update()` — see
     * `test_republicar_avance_ya_notificado_no_reenvia_el_job()` below, which
     * unpublishes then republishes an already-notified avance to reach it.
     */
    public function test_editar_avance_ya_notificado_no_reenvia_el_job(): void
    {
        Queue::fake();

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

        Queue::assertNotPushed(SendAvanceAportantesJob::class);

        $fresh = $avance->fresh();
        $this->assertNotNull($fresh->notificado_at);
        $this->assertSame(
            $notificadoAt->toDateTimeString(),
            $fresh->notificado_at->toDateTimeString(),
        );
    }

    /**
     * The only reachable path to the inner `notificado_at === null` guard
     * inside `despacharNotificacionSiCorresponde()`: an avance that is
     * already published AND already notified gets unpublished
     * (`publicado=false`), then republished (`publicado=true`) in a second
     * request. At that second update, `$estabaPublicado` is captured as
     * `false` (it was just unpublished), so the outer `! $estabaPublicado`
     * guard in `update()` does NOT block the call — control reaches
     * `despacharNotificacionSiCorresponde()`, whose own
     * `notificado_at === null` check must correctly prevent a second dispatch
     * since `notificado_at` is already set from the original notification.
     */
    public function test_republicar_avance_ya_notificado_no_reenvia_el_job(): void
    {
        Queue::fake();

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
                'titulo' => 'Despublicado temporalmente',
                'tipo' => 'general',
                'publicado' => false,
                'notificar_aportantes' => true,
            ])
            ->assertOk();

        $this->actingAs($autor, 'sanctum')
            ->patchJson("/api/iniciativas/{$iniciativa->uuid}/avances/{$avance->id}", [
                'titulo' => 'Republicado tras despublicar',
                'tipo' => 'general',
                'publicado' => true,
                'notificar_aportantes' => true,
            ])
            ->assertOk();

        Queue::assertNotPushed(SendAvanceAportantesJob::class);

        $fresh = $avance->fresh();
        $this->assertNotNull($fresh->notificado_at);
        $this->assertSame(
            $notificadoAt->toDateTimeString(),
            $fresh->notificado_at->toDateTimeString(),
        );
    }
}
