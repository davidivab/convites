<?php

namespace Tests\Feature;

use App\Models\Disponibilidad;
use App\Models\Habilidad;
use App\Models\Municipio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * [P53] Parte 2 — perfil extendido en registro / perfil.
 *
 * Cubre: columna `barrio`, campos opcionales de perfil en
 * `AuthController::register`, y el flag `needs_onboarding` en los payloads
 * de usuario (register, login, profile show/update).
 *
 * El caso de Google (`completarRegistro`) se cubre en
 * `GoogleAuthIntentTest`, que ya monta el flujo completo con Socialite fake.
 */
class PerfilExtendidoTest extends TestCase
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

    private function municipio(): Municipio
    {
        return Municipio::query()->create([
            'departamento_id' => \App\Models\Departamento::query()->create([
                'external_id' => 999,
                'nombre' => 'Depto Test',
                'slug' => 'depto-test-'.uniqid(),
                'orden' => 1,
            ])->id,
            'external_id' => 999,
            'nombre' => 'Municipio Test',
            'slug' => 'municipio-test-'.uniqid(),
            'activo' => true,
            'orden' => 1,
        ]);
    }

    public function test_register_acepta_perfil_extendido_opcional_y_sincroniza_pivots(): void
    {
        Queue::fake();

        $municipio = $this->municipio();
        $habilidadIds = Habilidad::query()->inRandomOrder()->limit(2)->pluck('id')->all();
        $disponibilidadIds = Disponibilidad::query()->inRandomOrder()->limit(1)->pluck('id')->all();

        // Si el seeder de catálogos no corrió (no se llamó en este test),
        // creamos los mínimos necesarios directamente.
        if (empty($habilidadIds)) {
            $habilidadIds = [
                Habilidad::query()->create(['slug' => 'h1', 'nombre' => 'H1', 'tipo' => \App\Enums\TipoHabilidad::Manual, 'orden' => 1, 'activo' => true])->id,
                Habilidad::query()->create(['slug' => 'h2', 'nombre' => 'H2', 'tipo' => \App\Enums\TipoHabilidad::Manual, 'orden' => 2, 'activo' => true])->id,
            ];
        }
        if (empty($disponibilidadIds)) {
            $disponibilidadIds = [
                Disponibilidad::query()->create(['slug' => 'd1', 'nombre' => 'D1', 'orden' => 1, 'activo' => true])->id,
            ];
        }

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Persona Perfil Completo',
            'email' => 'perfil.completo@convites.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'celular' => '+57 300 111 2233',
            'municipio_id' => $municipio->id,
            'barrio' => 'El Prado',
            'genero' => 'mujer',
            'edad' => 30,
            'aptitud_fisica' => 'media',
            'notas_salud' => 'Ninguna',
            'habilidad_ids' => $habilidadIds,
            'disponibilidad_ids' => $disponibilidadIds,
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.needs_onboarding', false);

        $user = User::query()->where('email', 'perfil.completo@convites.test')->firstOrFail();
        $this->assertSame($municipio->id, $user->municipio_id);
        $this->assertSame('El Prado', $user->barrio);
        $this->assertSame('mujer', $user->genero->value);
        $this->assertSame(30, $user->edad);
        $this->assertSame('media', $user->aptitud_fisica->value);
        $this->assertSame('Ninguna', $user->notas_salud);
        $this->assertCount(2, $user->habilidades);
        $this->assertCount(1, $user->disponibilidades);
    }

    public function test_register_sin_perfil_extendido_no_rompe_y_marca_needs_onboarding(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Persona Sin Perfil',
            'email' => 'sin.perfil@convites.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.needs_onboarding', true);
    }

    public function test_register_rechaza_genero_invalido(): void
    {
        Queue::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Persona Genero Invalido',
            'email' => 'genero.invalido@convites.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'genero' => 'no-existe',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['genero']);
    }

    public function test_register_rechaza_habilidad_id_inexistente(): void
    {
        Queue::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Persona Habilidad Invalida',
            'email' => 'habilidad.invalida@convites.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'habilidad_ids' => [999999],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['habilidad_ids.0']);
    }

    public function test_login_devuelve_needs_onboarding(): void
    {
        $member = $this->member();

        $response = $this->postJson('/api/auth/login', [
            'email' => $member->email,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonStructure(['user' => ['needs_onboarding']]);
        // Sin municipio_id ni habilidades/disponibilidades → true.
        $response->assertJsonPath('user.needs_onboarding', true);
    }

    public function test_profile_show_expone_barrio_y_needs_onboarding(): void
    {
        $member = $this->member();
        $municipio = $this->municipio();
        $member->forceFill(['municipio_id' => $municipio->id, 'barrio' => 'Centro'])->save();

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/profile');

        $response->assertOk()
            ->assertJsonPath('data.barrio', 'Centro')
            // Tiene municipio pero ninguna habilidad/disponibilidad → sigue en onboarding.
            ->assertJsonPath('data.needs_onboarding', true);
    }

    public function test_profile_update_persiste_barrio_y_habilidades_resuelve_needs_onboarding(): void
    {
        $member = $this->member();
        $municipio = $this->municipio();
        $habilidadId = Habilidad::query()->create([
            'slug' => 'h-update', 'nombre' => 'H Update', 'tipo' => \App\Enums\TipoHabilidad::Manual, 'orden' => 1, 'activo' => true,
        ])->id;

        $response = $this->actingAs($member, 'sanctum')->putJson('/api/profile', [
            'municipio_id' => $municipio->id,
            'barrio' => 'La Loma',
            'habilidad_ids' => [$habilidadId],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.barrio', 'La Loma')
            ->assertJsonPath('data.needs_onboarding', false);

        $this->assertSame('La Loma', $member->fresh()->barrio);
    }
}
