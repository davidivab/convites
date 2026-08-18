<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Encontrado en producción (2026-08-18): un request sin auth y SIN
 * `Accept: application/json` (ej. `curl` a pelo, como haría cualquier
 * bot/monitor externo) a una ruta protegida devolvía 500 en vez de 401.
 *
 * Causa: `Authenticate::redirectTo()` de Laravel, cuando el request no
 * "expectsJson()", intenta `route('login')` para redirigir — pero esta
 * API no tiene ninguna ruta web llamada `login` (es 100% API), así que
 * explota con `RouteNotFoundException` antes de llegar a nuestro
 * `shouldRenderJsonWhen` (eso solo controla el renderizado de la
 * excepción, no si Laravel intenta redirigir primero).
 */
class GuestSinAuthHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_ruta_protegida_sin_accept_json_devuelve_401_no_500(): void
    {
        $this->get('/api/mis-solicitudes-rol')
            ->assertUnauthorized();
    }

    public function test_ruta_protegida_con_accept_json_devuelve_401(): void
    {
        $this->getJson('/api/mis-solicitudes-rol')
            ->assertUnauthorized();
    }
}
