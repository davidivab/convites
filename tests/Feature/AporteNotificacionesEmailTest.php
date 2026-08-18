<?php

namespace Tests\Feature;

use App\Enums\EstadoAporte;
use App\Jobs\SendAporteAprobadoJob;
use App\Jobs\SendAporteRecibidoJob;
use App\Mail\AporteAprobadoMail;
use App\Mail\AporteRecibidoMail;
use App\Models\Aporte;
use App\Models\Iniciativa;
use App\Models\Municipio;
use App\Models\User;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Correos de aportes: al creador cuando alguien se compromete (aporte
 * confirmado/"recibido"), y al aportante cuando el creador confirma que
 * lo recibió de verdad ("aprobado").
 */
class AporteNotificacionesEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
    }

    private function municipioActivo(): Municipio
    {
        return Municipio::query()->where('activo', true)->firstOrFail();
    }

    public function test_confirmar_aporte_encola_correo_al_creador(): void
    {
        Queue::fake();

        $creador = User::factory()->create();
        $creador->assignRole('member');
        $municipio = $this->municipioActivo();
        $ini = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
        ]);

        $aportante = User::factory()->create();
        $aportante->assignRole('member');

        $this->actingAs($aportante, 'sanctum')
            ->postJson("/api/iniciativas/{$ini->id}/aportes", ['asiste_al_convite' => true])
            ->assertCreated();

        Queue::assertPushed(SendAporteRecibidoJob::class);
    }

    public function test_job_de_aporte_recibido_manda_correo_al_creador_sin_revelar_al_aportante(): void
    {
        Mail::fake();

        $creador = User::factory()->create(['name' => 'Ana Creadora']);
        $municipio = $this->municipioActivo();
        $ini = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'titulo' => 'Convite de Ana',
        ]);
        $aportante = User::factory()->create(['name' => 'Nombre Secreto']);
        $aporte = Aporte::query()->create([
            'iniciativa_id' => $ini->id,
            'user_id' => $aportante->id,
            'estado' => EstadoAporte::Confirmado,
            'asiste_al_convite' => true,
            'anonimo' => true,
            'confirmado_at' => now(),
        ]);

        (new SendAporteRecibidoJob($aporte))->handle();

        Mail::assertSent(AporteRecibidoMail::class, function ($mail) use ($creador) {
            $rendered = $mail->render();

            return $mail->hasTo($creador->email)
                && str_contains($rendered, 'Convite de Ana')
                && ! str_contains($rendered, 'Nombre Secreto');
        });
    }

    public function test_marcar_recepcion_encola_correo_al_aportante(): void
    {
        Queue::fake();

        $creador = User::factory()->create();
        $creador->assignRole('member');
        $municipio = $this->municipioActivo();
        $ini = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
        ]);
        $aportante = User::factory()->create();
        $aporte = Aporte::query()->create([
            'iniciativa_id' => $ini->id,
            'user_id' => $aportante->id,
            'estado' => EstadoAporte::Confirmado,
            'asiste_al_convite' => true,
            'confirmado_at' => now(),
        ]);

        $this->actingAs($creador, 'sanctum')
            ->post("/api/aportes/{$aporte->id}/recepcion", ['recibido' => true])
            ->assertOk();

        Queue::assertPushed(SendAporteAprobadoJob::class, fn ($job) => $job->aporte->is($aporte));
    }

    public function test_job_de_aporte_aprobado_manda_correo_al_aportante(): void
    {
        Mail::fake();

        $municipio = $this->municipioActivo();
        $ini = Iniciativa::factory()->publicada()->create(['municipio_id' => $municipio->id, 'titulo' => 'Convite X']);
        $aportante = User::factory()->create(['name' => 'Esteban Quintero']);
        $aporte = Aporte::query()->create(['iniciativa_id' => $ini->id, 'user_id' => $aportante->id, 'estado' => EstadoAporte::Confirmado, 'asiste_al_convite' => true, 'confirmado_at' => now()]);

        (new SendAporteAprobadoJob($aporte))->handle();

        Mail::assertSent(AporteAprobadoMail::class, fn ($mail) => $mail->hasTo($aportante->email)
            && str_contains($mail->render(), 'Convite X'));
    }
}
