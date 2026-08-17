<?php

namespace Tests\Feature;

use App\Enums\EstadoSolicitudProfesional;
use App\Enums\PreferenciaContacto;
use App\Models\Profesional;
use App\Models\ProfesionalSolicitud;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeProfesionalSolicitudesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_purges_solicitudes_older_than_retention(): void
    {
        $profesional = Profesional::factory()->aprobado()->create();

        $old = ProfesionalSolicitud::query()->create([
            'profesional_id' => $profesional->id,
            'nombre' => 'Viejo',
            'celular' => '3001112233',
            'preferencia_contacto' => PreferenciaContacto::Whatsapp,
            'mensaje' => 'Mensaje antiguo con PII',
            'estado' => EstadoSolicitudProfesional::Pendiente,
        ]);
        ProfesionalSolicitud::query()->whereKey($old->id)->update([
            'created_at' => now()->subDays(45),
            'updated_at' => now()->subDays(45),
        ]);

        $fresh = ProfesionalSolicitud::query()->create([
            'profesional_id' => $profesional->id,
            'nombre' => 'Nuevo',
            'celular' => '3009998877',
            'preferencia_contacto' => PreferenciaContacto::Whatsapp,
            'mensaje' => 'Mensaje reciente',
            'estado' => EstadoSolicitudProfesional::Pendiente,
        ]);
        ProfesionalSolicitud::query()->whereKey($fresh->id)->update([
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $this->artisan('convites:purge-profesional-solicitudes', ['--days' => 30])
            ->assertSuccessful();

        $this->assertDatabaseMissing('profesional_solicitudes', ['id' => $old->id]);
        $this->assertDatabaseHas('profesional_solicitudes', ['id' => $fresh->id]);
    }
}
