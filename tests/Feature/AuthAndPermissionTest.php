<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_login_and_access_permissioned_routes(): void
    {
        $this->seed();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'admin@convites.test',
            'password' => 'password',
        ]);

        $login->assertOk()->assertJsonStructure(['token', 'user' => ['roles', 'permissions']]);

        $token = $login->json('token');

        $this->withToken($token)->getJson('/api/dashboard')->assertOk();
        $this->withToken($token)->getJson('/api/users')->assertOk();
    }

    public function test_member_cannot_access_admin_permission(): void
    {
        $this->seed();

        $token = $this->postJson('/api/auth/login', [
            'email' => 'member@convites.test',
            'password' => 'password',
        ])->json('token');

        $this->withToken($token)->getJson('/api/dashboard')->assertOk();
        $this->withToken($token)->getJson('/api/users')->assertForbidden();
    }

    public function test_me_expone_municipio_ids_del_voluntario_demo(): void
    {
        $this->seed();

        $token = $this->postJson('/api/auth/login', [
            'email' => 'voluntario@convites.test',
            'password' => 'password',
        ])->json('token');

        $me = $this->withToken($token)->getJson('/api/auth/me');

        $me->assertOk()->assertJsonStructure(['user' => ['municipio_ids']]);
        $this->assertNotEmpty($me->json('user.municipio_ids'));
    }
}
