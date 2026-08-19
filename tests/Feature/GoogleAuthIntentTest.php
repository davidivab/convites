<?php

namespace Tests\Feature;

use App\Enums\TipoHabilidad;
use App\Models\Habilidad;
use App\Models\Municipio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAuthIntentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['services.google.frontend_callback_url' => 'http://localhost:3095/auth/google/callback']);
    }

    public function test_redirect_acepta_intent_login_y_register(): void
    {
        // Nota: el fake de Socialite devuelve una URL fija y no reenvía los
        // parámetros custom de `with()` — no se puede verificar acá el valor
        // real de `state` en la URL (eso es responsabilidad de la librería,
        // no de nuestro código). Solo confirmamos que el endpoint funciona
        // para ambos intents.
        Socialite::fake('google');

        $this->getJson('/api/auth/google/redirect?intent=login')
            ->assertOk()
            ->assertJsonStructure(['url']);

        $this->getJson('/api/auth/google/redirect?intent=register')
            ->assertOk()
            ->assertJsonStructure(['url']);
    }

    public function test_usuario_existente_sigue_logueando_directo_sin_importar_el_intent(): void
    {
        Queue::fake();
        $existente = User::factory()->create(['email' => 'ya.existe@convites.test']);
        $existente->assignRole('member');

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-existe-1',
            'email' => 'ya.existe@convites.test',
            'name' => 'Ya Existe',
        ]));

        $response = $this->get('/api/auth/google/callback?code=fake-code&state=login')->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringNotContainsString('needs_registration', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->postJson('/api/auth/google/exchange', ['code' => $query['code']])
            ->assertOk()
            ->assertJsonPath('user.email', 'ya.existe@convites.test');
    }

    public function test_usuario_nuevo_desde_ingresar_no_crea_cuenta_y_pide_completar_registro(): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-nuevo-1',
            'email' => 'nuevo.sin.cuenta@convites.test',
            'name' => 'Nuevo Sin Cuenta',
        ]));

        $response = $this->get('/api/auth/google/callback?code=fake-code&state=login')->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('needs_registration=1', $location);

        $this->assertNull(User::query()->where('email', 'nuevo.sin.cuenta@convites.test')->first());

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        // El código de "pendiente de registro" NO sirve para exchange normal.
        $this->postJson('/api/auth/google/exchange', ['code' => $query['code']])
            ->assertNotFound();
    }

    public function test_completar_registro_crea_la_cuenta_con_los_datos_del_formulario(): void
    {
        Queue::fake();

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-completar-1',
            'email' => 'completar.registro@convites.test',
            'name' => 'Persona Completa',
        ]));

        $callback = $this->get('/api/auth/google/callback?code=fake-code&state=register')->assertRedirect();
        parse_str((string) parse_url($callback->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->postJson('/api/auth/google/completar-registro', [
            'code' => $query['code'],
            'celular' => '+57 300 000 0000',
            'acepta_terminos' => true,
            'acepta_descargo' => true,
        ])
            ->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'email']])
            ->assertJsonPath('user.email', 'completar.registro@convites.test');

        $user = User::query()->where('email', 'completar.registro@convites.test')->firstOrFail();
        $this->assertSame('google-completar-1', $user->google_id);
        $this->assertNull($user->password);
        $this->assertTrue($user->hasRole('member'));
        $this->assertNotNull($user->acepta_terminos_at);

        // Un solo uso.
        $this->postJson('/api/auth/google/completar-registro', [
            'code' => $query['code'],
            'celular' => '+57 300 000 0000',
            'acepta_terminos' => true,
            'acepta_descargo' => true,
        ])->assertNotFound();
    }

    public function test_completar_registro_exige_aceptar_terminos_y_descargo(): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-sin-aceptar',
            'email' => 'sin.aceptar@convites.test',
            'name' => 'Sin Aceptar',
        ]));

        $callback = $this->get('/api/auth/google/callback?code=fake-code&state=register')->assertRedirect();
        parse_str((string) parse_url($callback->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->postJson('/api/auth/google/completar-registro', [
            'code' => $query['code'],
            'celular' => '+57 300 000 0000',
            'acepta_terminos' => false,
            'acepta_descargo' => true,
        ])->assertUnprocessable();
    }

    /**
     * [P53] Parte 2 — completar-registro acepta el mismo perfil extendido
     * opcional que `AuthController::register`.
     */
    public function test_completar_registro_acepta_perfil_extendido_opcional(): void
    {
        Queue::fake();

        $departamento = \App\Models\Departamento::query()->create([
            'external_id' => 998,
            'nombre' => 'Depto Google Test',
            'slug' => 'depto-google-test-'.uniqid(),
            'orden' => 1,
        ]);
        $municipio = Municipio::query()->create([
            'departamento_id' => $departamento->id,
            'external_id' => 998,
            'nombre' => 'Municipio Google Test',
            'slug' => 'municipio-google-test-'.uniqid(),
            'activo' => true,
            'orden' => 1,
        ]);
        $habilidadId = Habilidad::query()->create([
            'slug' => 'h-google-completar',
            'nombre' => 'H Google Completar',
            'tipo' => TipoHabilidad::Manual,
            'orden' => 1,
            'activo' => true,
        ])->id;

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-perfil-extendido',
            'email' => 'perfil.extendido.google@convites.test',
            'name' => 'Perfil Extendido Google',
        ]));

        $callback = $this->get('/api/auth/google/callback?code=fake-code&state=register')->assertRedirect();
        parse_str((string) parse_url($callback->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->postJson('/api/auth/google/completar-registro', [
            'code' => $query['code'],
            'celular' => '+57 300 000 0000',
            'acepta_terminos' => true,
            'acepta_descargo' => true,
            'municipio_id' => $municipio->id,
            'barrio' => 'Barrio Google',
            'genero' => 'hombre',
            'edad' => 40,
            'aptitud_fisica' => 'alta',
            'notas_salud' => 'Ninguna',
            'habilidad_ids' => [$habilidadId],
        ])
            ->assertCreated()
            ->assertJsonPath('user.needs_onboarding', false);

        $user = User::query()->where('email', 'perfil.extendido.google@convites.test')->firstOrFail();
        $this->assertSame($municipio->id, $user->municipio_id);
        $this->assertSame('Barrio Google', $user->barrio);
        $this->assertSame('hombre', $user->genero->value);
        $this->assertSame(40, $user->edad);
        $this->assertCount(1, $user->habilidades);
    }

    public function test_completar_registro_sin_perfil_extendido_marca_needs_onboarding(): void
    {
        Queue::fake();

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-sin-perfil',
            'email' => 'sin.perfil.google@convites.test',
            'name' => 'Sin Perfil Google',
        ]));

        $callback = $this->get('/api/auth/google/callback?code=fake-code&state=register')->assertRedirect();
        parse_str((string) parse_url($callback->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->postJson('/api/auth/google/completar-registro', [
            'code' => $query['code'],
            'acepta_terminos' => true,
            'acepta_descargo' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('user.needs_onboarding', true);
    }

    public function test_completar_registro_rechaza_aptitud_fisica_invalida(): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-aptitud-invalida',
            'email' => 'aptitud.invalida.google@convites.test',
            'name' => 'Aptitud Invalida',
        ]));

        $callback = $this->get('/api/auth/google/callback?code=fake-code&state=register')->assertRedirect();
        parse_str((string) parse_url($callback->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->postJson('/api/auth/google/completar-registro', [
            'code' => $query['code'],
            'acepta_terminos' => true,
            'acepta_descargo' => true,
            'aptitud_fisica' => 'no-existe',
        ])->assertUnprocessable();
    }
}
