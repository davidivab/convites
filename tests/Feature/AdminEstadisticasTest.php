<?php

namespace Tests\Feature;

use App\Enums\EstadoIniciativa;
use App\Models\Iniciativa;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminEstadisticasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_member_no_accede_estadisticas(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/admin/estadisticas')
            ->assertForbidden();
    }

    public function test_defaults_de_fecha_cuando_no_se_mandan_params(): void
    {
        $admin = $this->admin();

        $today = Carbon::now()->toDateString();
        $expectedStart = Carbon::now()->subWeeks(2)->toDateString();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/estadisticas')
            ->assertOk()
            ->assertJsonPath('start_date', $expectedStart)
            ->assertJsonPath('end_date', $today)
            ->assertJsonStructure([
                'start_date',
                'end_date',
                'usuarios_por_dia',
                'convites_por_dia',
                'convites_por_estado',
                'avance_global' => ['promedio', 'convites_considerados'],
            ]);
    }

    public function test_422_si_start_date_mayor_que_end_date(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/estadisticas?start_date=2026-08-19&end_date=2026-08-01')
            ->assertStatus(422);
    }

    public function test_usuarios_y_convites_por_dia_con_zero_fill(): void
    {
        $admin = $this->admin();

        // Rango fijo de 3 días.
        $start = '2026-08-10';
        $end = '2026-08-12';

        // Creador reusado explícitamente para que la Iniciativa no dispare
        // la creación implícita de un User (vía User::factory() de la
        // factory de Iniciativa) dentro del rango bajo prueba.
        $creador = User::factory()->create();

        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00'));
        User::factory()->create();
        Iniciativa::factory()->create(['user_id' => $creador->id]);
        Carbon::setTestNow();

        // Día 11 sin registros (debe quedar en 0).

        Carbon::setTestNow(Carbon::parse('2026-08-12 09:00:00'));
        User::factory()->count(2)->create();
        Carbon::setTestNow();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/estadisticas?start_date={$start}&end_date={$end}")
            ->assertOk();

        $response->assertJsonPath('usuarios_por_dia', [
            ['fecha' => '2026-08-10', 'total' => 1],
            ['fecha' => '2026-08-11', 'total' => 0],
            ['fecha' => '2026-08-12', 'total' => 2],
        ]);

        $response->assertJsonPath('convites_por_dia', [
            ['fecha' => '2026-08-10', 'total' => 1],
            ['fecha' => '2026-08-11', 'total' => 0],
            ['fecha' => '2026-08-12', 'total' => 0],
        ]);
    }

    public function test_convites_por_estado_filtra_por_fecha_convite_no_created_at(): void
    {
        $admin = $this->admin();

        $start = '2026-08-10';
        $end = '2026-08-12';

        // created_at fuera de rango, pero fecha_convite dentro: debe contar.
        Carbon::setTestNow(Carbon::parse('2026-01-01'));
        Iniciativa::factory()->create([
            'estado' => EstadoIniciativa::Publicada,
            'fecha_convite' => '2026-08-11',
        ]);
        Carbon::setTestNow();

        // created_at dentro de rango, pero fecha_convite fuera: NO debe contar.
        Carbon::setTestNow(Carbon::parse('2026-08-11'));
        Iniciativa::factory()->create([
            'estado' => EstadoIniciativa::Publicada,
            'fecha_convite' => '2027-01-01',
        ]);
        Carbon::setTestNow();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/estadisticas?start_date={$start}&end_date={$end}")
            ->assertOk();

        $publicada = collect($response->json('convites_por_estado'))
            ->firstWhere('estado', 'publicada');

        $this->assertSame(1, $publicada['total']);
    }

    public function test_iniciativas_con_fecha_convite_null_quedan_excluidas(): void
    {
        $admin = $this->admin();

        $start = '2026-08-10';
        $end = '2026-08-12';

        Iniciativa::factory()->create([
            'estado' => EstadoIniciativa::Borrador,
            'fecha_convite' => null,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/estadisticas?start_date={$start}&end_date={$end}")
            ->assertOk();

        $totalGeneral = collect($response->json('convites_por_estado'))->sum('total');
        $this->assertSame(0, $totalGeneral);
        $this->assertSame(0, $response->json('avance_global.convites_considerados'));
    }

    public function test_convites_por_estado_siempre_trae_las_6_claves_en_orden(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/estadisticas')
            ->assertOk();

        $estados = collect($response->json('convites_por_estado'))->pluck('estado')->all();

        $this->assertSame([
            'borrador',
            'en_revision',
            'publicada',
            'en_curso',
            'cerrada',
            'rechazada',
        ], $estados);
    }

    public function test_avance_global_promedia_progreso_cache_de_iniciativas_en_rango(): void
    {
        $admin = $this->admin();

        $start = '2026-08-10';
        $end = '2026-08-12';

        Iniciativa::factory()->create([
            'estado' => EstadoIniciativa::EnCurso,
            'fecha_convite' => '2026-08-11',
            'progreso_cache' => 50,
        ]);
        Iniciativa::factory()->create([
            'estado' => EstadoIniciativa::Cerrada,
            'fecha_convite' => '2026-08-12',
            'progreso_cache' => 100,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/estadisticas?start_date={$start}&end_date={$end}")
            ->assertOk();

        $response->assertJsonPath('avance_global.promedio', 75)
            ->assertJsonPath('avance_global.convites_considerados', 2);
    }

    public function test_avance_global_es_cero_cuando_no_hay_convites_considerados(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/estadisticas?start_date=2026-08-10&end_date=2026-08-12')
            ->assertOk();

        $response->assertJsonPath('avance_global.promedio', 0)
            ->assertJsonPath('avance_global.convites_considerados', 0);
    }
}
