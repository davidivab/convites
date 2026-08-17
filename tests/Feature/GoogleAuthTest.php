<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['services.google.frontend_callback_url' => 'http://localhost:3000/auth/google/callback']);
    }

    public function test_redirect_devuelve_una_url(): void
    {
        Socialite::fake('google');

        $this->getJson('/api/auth/google/redirect')
            ->assertOk()
            ->assertJsonStructure(['url']);
    }

    public function test_callback_de_usuario_nuevo_no_crea_cuenta_pide_completar_registro(): void
    {
        // P47: ya no se crea la cuenta en el callback — ver GoogleAuthIntentTest
        // para el flujo completo (callback → completar-registro).
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-123',
            'email' => 'nuevo.google@convites.test',
            'name' => 'Nuevo Por Google',
        ]));

        $response = $this->get('/api/auth/google/callback?code=fake-code');

        $response->assertRedirect();
        $this->assertStringStartsWith('http://localhost:3000/auth/google/callback?code=', $response->headers->get('Location'));
        $this->assertStringContainsString('needs_registration=1', $response->headers->get('Location'));

        $this->assertNull(User::query()->where('email', 'nuevo.google@convites.test')->first());
    }

    public function test_callback_vincula_cuenta_existente_por_email(): void
    {
        $existente = User::factory()->create(['email' => 'ya.registrado@convites.test']);
        $existente->assignRole('member');

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-456',
            'email' => 'ya.registrado@convites.test',
            'name' => 'Nombre De Google',
        ]));

        $this->get('/api/auth/google/callback?code=fake-code')->assertRedirect();

        $this->assertSame(1, User::query()->where('email', 'ya.registrado@convites.test')->count());
        $this->assertSame('google-456', $existente->fresh()->google_id);
    }

    public function test_exchange_devuelve_token_y_es_de_un_solo_uso(): void
    {
        // exchange es solo para cuentas ya existentes (P47) — el caso "usuario
        // nuevo" usa completar-registro, no exchange (ver GoogleAuthIntentTest).
        $existente = User::factory()->create(['email' => 'exchange@convites.test']);
        $existente->assignRole('member');

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-789',
            'email' => 'exchange@convites.test',
            'name' => 'Test Exchange',
        ]));

        $callback = $this->get('/api/auth/google/callback?code=fake-code');
        $location = $callback->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $exchangeCode = $query['code'];

        $this->postJson('/api/auth/google/exchange', ['code' => $exchangeCode])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email']])
            ->assertJsonPath('user.email', 'exchange@convites.test');

        // Un solo uso: la segunda vez falla.
        $this->postJson('/api/auth/google/exchange', ['code' => $exchangeCode])
            ->assertNotFound();
    }

    public function test_exchange_con_codigo_invalido_da_404(): void
    {
        $this->postJson('/api/auth/google/exchange', ['code' => 'no-existe'])
            ->assertNotFound();
    }
}
