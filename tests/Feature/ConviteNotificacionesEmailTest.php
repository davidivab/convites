<?php

namespace Tests\Feature;

use App\Enums\EstadoIniciativa;
use App\Jobs\SendConviteAprobadoJob;
use App\Jobs\SendConviteEnviadoRevisionJob;
use App\Mail\ConviteAprobadoMail;
use App\Mail\ConviteEnviadoRevisionMail;
use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use App\Models\Municipio;
use App\Models\User;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Correos al creador del convite: al enviarlo a revisión y al ser aprobado.
 */
class ConviteNotificacionesEmailTest extends TestCase
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

    public function test_enviar_a_revision_encola_correo_al_creador(): void
    {
        Queue::fake();

        $creador = User::factory()->create();
        $creador->assignRole('member');
        $municipio = $this->municipioActivo();
        $ini = Iniciativa::factory()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
            'estado' => EstadoIniciativa::Borrador,
        ]);
        IniciativaItem::query()->create([
            'iniciativa_id' => $ini->id,
            'nombre' => 'Arena',
            'unidad' => 'bultos',
            'cantidad_meta' => 5,
            'cantidad_aportada' => 0,
            'orden' => 1,
        ]);

        $this->actingAs($creador, 'sanctum')
            ->postJson('/api/iniciativas/'.$ini->id.'/enviar-revision')
            ->assertOk();

        Queue::assertPushed(SendConviteEnviadoRevisionJob::class, fn ($job) => $job->iniciativa->is($ini));
    }

    public function test_job_de_enviado_a_revision_manda_el_correo_al_creador(): void
    {
        Mail::fake();

        $creador = User::factory()->create(['name' => 'Rosa Elena']);
        $municipio = $this->municipioActivo();
        $ini = Iniciativa::factory()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'titulo' => 'Ayuda para Rosa',
        ]);

        (new SendConviteEnviadoRevisionJob($ini))->handle();

        Mail::assertSent(ConviteEnviadoRevisionMail::class, fn ($mail) => $mail->hasTo($creador->email)
            && str_contains($mail->render(), 'Ayuda para Rosa'));
    }

    public function test_aprobar_convite_encola_correo_al_creador(): void
    {
        Queue::fake();

        $moderador = User::factory()->create();
        $moderador->assignRole('moderator');
        $municipio = $this->municipioActivo();
        $moderador->municipiosAsignados()->sync([$municipio->id]);

        $creador = User::factory()->create();
        $creador->assignRole('member');
        $ini = Iniciativa::factory()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
            'estado' => EstadoIniciativa::EnRevision,
        ]);

        $this->actingAs($moderador, 'sanctum')
            ->postJson('/api/moderacion/iniciativas/'.$ini->id.'/aprobar')
            ->assertOk();

        Queue::assertPushed(SendConviteAprobadoJob::class, fn ($job) => $job->iniciativa->is($ini));
    }

    public function test_job_de_convite_aprobado_manda_el_correo_al_creador(): void
    {
        Mail::fake();

        $creador = User::factory()->create(['name' => 'Diego Salazar']);
        $municipio = $this->municipioActivo();
        $ini = Iniciativa::factory()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'titulo' => 'Techos para Diego',
        ]);

        (new SendConviteAprobadoJob($ini))->handle();

        Mail::assertSent(ConviteAprobadoMail::class, fn ($mail) => $mail->hasTo($creador->email)
            && str_contains($mail->render(), 'Techos para Diego'));
    }
}
