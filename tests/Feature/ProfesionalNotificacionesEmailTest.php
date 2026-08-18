<?php

namespace Tests\Feature;

use App\Enums\EstadoProfesional;
use App\Jobs\SendProfesionalAprobadoJob;
use App\Jobs\SendProfesionalRegistradoJob;
use App\Mail\ProfesionalAprobadoMail;
use App\Mail\ProfesionalRegistradoMail;
use App\Models\Municipio;
use App\Models\Profesional;
use App\Models\User;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProfesionalNotificacionesEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
    }

    public function test_registrar_perfil_profesional_encola_correo_de_confirmacion(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $user->assignRole('member');
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profesionales', [
                'municipio_id' => $municipio->id,
                'area' => 'psicologia',
                'nombre' => 'Laura Cardona',
                'titulo' => 'Psicóloga clínica',
                'email' => 'laura@convites.test',
                'modalidad' => 'presencial',
                'disponibilidad' => 'Martes y jueves',
                'descripcion' => 'Acompañamiento emocional.',
            ])
            ->assertCreated();

        Queue::assertPushed(SendProfesionalRegistradoJob::class);
    }

    public function test_job_de_profesional_registrado_manda_correo_de_confirmacion(): void
    {
        Mail::fake();

        $user = User::factory()->create(['name' => 'Laura Cardona']);
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $profesional = Profesional::query()->create([
            'user_id' => $user->id,
            'municipio_id' => $municipio->id,
            'area' => 'psicologia',
            'nombre' => 'Laura Cardona',
            'titulo' => 'Psicóloga clínica',
            'email' => 'laura@convites.test',
            'modalidad' => 'presencial',
            'disponibilidad' => 'Martes y jueves',
            'descripcion' => 'Acompañamiento emocional.',
            'inicial' => 'L',
            'estado' => EstadoProfesional::Pendiente,
            'enviado_at' => now(),
            'acepta_terminos_at' => now(),
        ]);

        (new SendProfesionalRegistradoJob($profesional))->handle();

        Mail::assertSent(ProfesionalRegistradoMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_aprobar_perfil_profesional_encola_correo(): void
    {
        Queue::fake();

        $moderador = User::factory()->create();
        $moderador->assignRole('moderator');

        $user = User::factory()->create();
        $user->assignRole('member');
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $profesional = Profesional::query()->create([
            'user_id' => $user->id,
            'municipio_id' => $municipio->id,
            'area' => 'legal',
            'nombre' => 'Juan Pérez',
            'titulo' => 'Abogado',
            'email' => 'juan@convites.test',
            'modalidad' => 'virtual',
            'disponibilidad' => 'Fines de semana',
            'descripcion' => 'Asesoría legal.',
            'inicial' => 'J',
            'estado' => EstadoProfesional::Pendiente,
            'enviado_at' => now(),
            'acepta_terminos_at' => now(),
        ]);

        $this->actingAs($moderador, 'sanctum')
            ->postJson("/api/moderacion/profesionales/{$profesional->id}/aprobar")
            ->assertOk();

        Queue::assertPushed(SendProfesionalAprobadoJob::class, fn ($job) => $job->profesional->is($profesional));
    }

    public function test_job_de_profesional_aprobado_manda_correo(): void
    {
        Mail::fake();

        $user = User::factory()->create(['name' => 'Juan Pérez']);
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $profesional = Profesional::query()->create([
            'user_id' => $user->id,
            'municipio_id' => $municipio->id,
            'area' => 'legal',
            'nombre' => 'Juan Pérez',
            'titulo' => 'Abogado',
            'email' => 'juan@convites.test',
            'modalidad' => 'virtual',
            'disponibilidad' => 'Fines de semana',
            'descripcion' => 'Asesoría legal.',
            'inicial' => 'J',
            'estado' => EstadoProfesional::Aprobado,
            'enviado_at' => now(),
            'aprobado_at' => now(),
            'acepta_terminos_at' => now(),
        ]);

        (new SendProfesionalAprobadoJob($profesional))->handle();

        Mail::assertSent(ProfesionalAprobadoMail::class, fn ($mail) => $mail->hasTo($user->email));
    }
}
