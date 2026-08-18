<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * [P50] Sin lang/es/validation.php, un locale "es" sin traducciones
 * publicadas devolvía las claves crudas (ej. "validation.required") en
 * vez de un mensaje legible — el front no podía mostrar nada útil.
 */
class ValidacionMensajesEspanolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        return $user;
    }

    public function test_crear_iniciativa_invalida_devuelve_mensajes_en_espanol(): void
    {
        $response = $this->actingAs($this->member(), 'sanctum')
            ->postJson('/api/iniciativas', ['titulo' => '', 'items' => []])
            ->assertStatus(422);

        $body = $response->json();

        $this->assertStringNotContainsString('validation.', $body['message']);
        $this->assertStringContainsString('obligatorio', $body['message']);
        $this->assertStringContainsString('título', implode(' ', $body['errors']['titulo']));
        $this->assertStringContainsString('obligatorio', $body['errors']['titulo'][0]);
    }

    public function test_registro_con_password_corto_devuelve_mensaje_en_espanol(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ana',
            'email' => 'ana@convites.test',
            'password' => '123',
            'password_confirmation' => '123',
        ])->assertStatus(422);

        $errores = $response->json('errors');
        $this->assertNotEmpty($errores);

        foreach ($errores as $mensajes) {
            foreach ($mensajes as $mensaje) {
                $this->assertStringNotContainsString('validation.', $mensaje);
            }
        }
    }
}
