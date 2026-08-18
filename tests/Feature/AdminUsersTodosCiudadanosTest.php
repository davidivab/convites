<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La pestaña "Usuarios" del admin solo listaba moderador/voluntario por
 * diseño (P19-P21) — no existía forma de ver ciudadanos comunes (member).
 * Encontrado en producción 2026-08-18: el usuario pensó que esa pestaña
 * mostraba a todos los registrados. Se agrega `?todos=1` para una futura
 * pestaña "Ciudadanos" sin romper el comportamiento actual de
 * Usuarios/Moderadores/Voluntarios.
 */
class AdminUsersTodosCiudadanosTest extends TestCase
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

    public function test_sin_filtros_mantiene_solo_moderador_y_voluntario(): void
    {
        $moderador = User::factory()->create();
        $moderador->assignRole('moderator');
        $ciudadano = User::factory()->create();
        $ciudadano->assignRole('member');

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/users')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($moderador->id, $ids);
        $this->assertNotContains($ciudadano->id, $ids);
    }

    public function test_todos_lista_tambien_ciudadanos_member(): void
    {
        $moderador = User::factory()->create();
        $moderador->assignRole('moderator');
        $ciudadano = User::factory()->create(['name' => 'Ciudadano Demo']);
        $ciudadano->assignRole('member');

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/users?todos=1')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($moderador->id, $ids);
        $this->assertContains($ciudadano->id, $ids);
    }

    public function test_todos_con_q_busca_por_nombre_correo_o_celular(): void
    {
        $encontrado = User::factory()->create(['name' => 'Rosa Elena Duque', 'email' => 'rosa@convites.test']);
        $encontrado->assignRole('member');
        $otro = User::factory()->create(['name' => 'Otro Ciudadano', 'email' => 'otro@convites.test']);
        $otro->assignRole('member');

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/users?todos=1&q=rosa')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$encontrado->id], $ids);
    }

    public function test_member_no_puede_ver_el_listado_de_usuarios(): void
    {
        $ciudadano = User::factory()->create();
        $ciudadano->assignRole('member');

        $this->actingAs($ciudadano, 'sanctum')
            ->getJson('/api/admin/users?todos=1')
            ->assertForbidden();
    }
}
